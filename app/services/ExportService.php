<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Generation des fichiers PDF et Excel dans storage/exports (non public).
 */
final class ExportService
{
    public static function dossierExports(): string
    {
        $dossier = dirname(__DIR__, 2) . '/storage/exports';
        if (!is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }

        return $dossier;
    }

    /**
     * @param array<string, mixed> $rapport
     */
    public static function pdf(array $rapport): string
    {
        return self::rendrePdf('rapports/pdf', ['rapport' => $rapport], 'rapport-' . $rapport['type']);
    }

    /**
     * @param array<string, mixed> $donnees
     */
    public static function recu(array $donnees): string
    {
        return self::rendrePdf('rapports/recu', $donnees, 'recu-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($donnees['paiement']['numero_recu'] ?? 'paiement')));
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private static function rendrePdf(string $vue, array $donnees, string $prefixe): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', dirname(__DIR__, 2));

        $dompdf = new Dompdf($options);

        extract($donnees, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__, 2) . '/resources/views/' . $vue . '.php';
        $html = (string) ob_get_clean();

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $fichier = self::dossierExports() . '/' . self::nomFichier($prefixe, 'pdf');
        file_put_contents($fichier, $dompdf->output());

        self::nettoyer();

        return $fichier;
    }

    /**
     * @param array<string, mixed> $rapport
     */
    public static function excel(array $rapport): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('NAYGAL — Centre Manager')
            ->setTitle((string) $rapport['titre'])
            ->setDescription((string) $rapport['sous_titre']);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr((string) $rapport['titre'], 0, 31));

        $ligne = 1;
        $sheet->setCellValue('A' . $ligne, (string) $rapport['centre']['nom']);
        $sheet->mergeCells('A' . $ligne . ':D' . $ligne);
        $sheet->getStyle('A' . $ligne)->getFont()->setBold(true)->setSize(14);
        $ligne++;

        $sheet->setCellValue('A' . $ligne, (string) $rapport['titre']);
        $sheet->mergeCells('A' . $ligne . ':D' . $ligne);
        $sheet->getStyle('A' . $ligne)->getFont()->setBold(true)->setSize(12);
        $ligne++;

        $sheet->setCellValue('A' . $ligne, (string) $rapport['sous_titre']);
        $sheet->mergeCells('A' . $ligne . ':D' . $ligne);
        $ligne++;

        $sheet->setCellValue('A' . $ligne, 'Généré le ' . $rapport['generee_le'] . ' — ' . $rapport['filtres_texte']);
        $sheet->mergeCells('A' . $ligne . ':D' . $ligne);
        $sheet->getStyle('A' . $ligne)->getFont()->setItalic(true)->setSize(9);
        $ligne += 2;

        if ($rapport['resume'] !== []) {
            $sheet->setCellValue('A' . $ligne, 'Synthèse');
            $sheet->getStyle('A' . $ligne)->getFont()->setBold(true);
            $ligne++;
            foreach ($rapport['resume'] as $item) {
                $sheet->setCellValue('A' . $ligne, (string) $item['libelle']);
                $valeur = $item['valeur'];
                $sheet->setCellValue('B' . $ligne, is_numeric($valeur) ? (float) $valeur : (string) $valeur);
                if (($item['type'] ?? '') === 'money') {
                    $sheet->getStyle('B' . $ligne)->getNumberFormat()->setFormatCode('#,##0');
                }
                $ligne++;
            }
            $ligne++;
        }

        foreach ($rapport['sections'] as $section) {
            $sheet->setCellValue('A' . $ligne, (string) $section['titre']);
            $sheet->getStyle('A' . $ligne)->getFont()->setBold(true)->setSize(11);
            $ligne++;

            $colonne = 1;
            foreach ($section['colonnes'] as $col) {
                $lettre = self::lettreColonne($colonne);
                $sheet->setCellValue($lettre . $ligne, (string) $col['libelle']);
                $sheet->getStyle($lettre . $ligne)->getFont()->setBold(true);
                $sheet->getStyle($lettre . $ligne)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E9ECEF');
                $sheet->getColumnDimension($lettre)->setWidth(max(12, min(40, mb_strlen((string) $col['libelle']) + 6)));
                $colonne++;
            }
            $ligne++;

            $premiereLigneDonnees = $ligne;

            foreach ($section['lignes'] as $row) {
                $colonne = 1;
                foreach ($section['colonnes'] as $col) {
                    $lettre = self::lettreColonne($colonne);
                    $valeur = $row[$col['cle']] ?? '';
                    $type = $col['type'] ?? 'texte';

                    if ($type === 'money' || $type === 'int') {
                        $sheet->setCellValue($lettre . $ligne, is_numeric($valeur) ? (float) $valeur : 0);
                        if ($type === 'money') {
                            $sheet->getStyle($lettre . $ligne)->getNumberFormat()->setFormatCode('#,##0');
                        }
                    } else {
                        $sheet->setCellValue($lettre . $ligne, (string) $valeur);
                    }
                    $colonne++;
                }
                $ligne++;
            }

            if ($section['lignes'] === []) {
                $sheet->setCellValue('A' . $ligne, 'Aucune donnée pour ces critères.');
                $ligne++;
            }

            if (($section['totaux'] ?? []) !== []) {
                $premiereLettre = 'A';
                $sheet->setCellValue('A' . $ligne, 'TOTAL');
                $sheet->getStyle('A' . $ligne)->getFont()->setBold(true);

                $colonne = 1;
                foreach ($section['colonnes'] as $col) {
                    if (array_key_exists($col['cle'], $section['totaux']) && $col['cle'] !== 'nb') {
                        $lettre = self::lettreColonne($colonne);
                        $sheet->setCellValue($lettre . $ligne, (float) $section['totaux'][$col['cle']]);
                        if (($col['type'] ?? '') === 'money') {
                            $sheet->getStyle($lettre . $ligne)->getNumberFormat()->setFormatCode('#,##0');
                        }
                        $sheet->getStyle($lettre . $ligne)->getFont()->setBold(true);
                    }
                    $colonne++;
                }
                $sheet->getStyle('A' . $ligne . ':' . self::lettreColonne(count($section['colonnes'])) . $ligne)
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                $ligne++;
            }

            if ($section['lignes'] !== []) {
                $derniereColonne = self::lettreColonne(count($section['colonnes']));
                $sheet->getStyle('A' . $premiereLigneDonnees . ':' . $derniereColonne . ($ligne - 1))
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A' . $premiereLigneDonnees . ':' . $derniereColonne . ($ligne - 1))
                    ->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            }

            $ligne += 2;
        }

        $sheet->setCellValue('A' . $ligne, 'Document généré le ' . date('d/m/Y H:i') . ' — Solution développée par NAYGAL');
        $sheet->getStyle('A' . $ligne)->getFont()->setSize(8)->setItalic(true);

        $fichier = self::dossierExports() . '/' . self::nomFichier('rapport-' . $rapport['type'], 'xlsx');
        (new Xlsx($spreadsheet))->save($fichier);
        $spreadsheet->disconnectWorksheets();

        self::nettoyer();

        return $fichier;
    }

    public static function nomFichier(string $prefixe, string $extension): string
    {
        $prefixe = preg_replace('/[^A-Za-z0-9_-]/', '-', $prefixe) ?? 'export';

        return $prefixe . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    }

    public static function fichierAutorise(string $nom): bool
    {
        return (bool) preg_match('/^(rapport|recu)-[A-Za-z0-9_-]+\.(pdf|xlsx)$/', $nom);
    }

    public static function cheminTelechargement(string $nom): ?string
    {
        $nom = basename($nom);
        if (!self::fichierAutorise($nom)) {
            return null;
        }

        $chemin = self::dossierExports() . '/' . $nom;

        return is_file($chemin) ? $chemin : null;
    }

    public static function nettoyer(int $jours = 2): void
    {
        $dossier = self::dossierExports();
        $limite = time() - ($jours * 86400);

        foreach (glob($dossier . '/*') ?: [] as $fichier) {
            if (is_file($fichier) && filemtime($fichier) !== false && filemtime($fichier) < $limite) {
                @unlink($fichier);
            }
        }
    }

    private static function lettreColonne(int $numero): string
    {
        $lettre = '';
        while ($numero > 0) {
            $modulo = ($numero - 1) % 26;
            $lettre = chr(65 + $modulo) . $lettre;
            $numero = intdiv($numero - 1, 26);
        }

        return $lettre;
    }

    public static function logErreur(string $message): void
    {
        Database::log($message);
    }
}
