<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'settings' => Setting::getAllAsArray(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_name'     => 'required|string|max:100',
            'store_address'  => 'nullable|string|max:300',
            'store_phone'    => 'nullable|string|max:30',
            'store_email'    => 'nullable|email|max:100',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'receipt_header' => 'nullable|string|max:200',
            'receipt_footer' => 'nullable|string|max:200',
            'low_stock_alert'=> 'boolean',
            'receipt_paper_width' => 'required|string|in:58mm,80mm',
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        ActivityLog::log('Edit Pengaturan', 'Mengubah pengaturan toko.');

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate(['logo' => 'required|image|max:2048']);

        $old = Setting::get('store_logo');
        if ($old) Storage::disk('public')->delete($old);

        $path = $request->file('logo')->store('settings', 'public');
        Setting::set('store_logo', $path);

        ActivityLog::log('Upload Logo', 'Mengganti logo toko.');

        return back()->with('success', 'Logo berhasil diupload.');
    }

    public function backup(): \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse
    {
        try {
            $connection = config('database.default');
            $dbName = config("database.connections.{$connection}.database");

            if ($connection !== 'mysql') {
                return back()->with('error', 'Fitur backup saat ini hanya mendukung database MySQL.');
            }

            $tablesResult = \DB::select('SHOW TABLES');
            $tablesKey = "Tables_in_{$dbName}";

            $sqlDump = "-- Kasir POS Database Backup\n";
            $sqlDump .= "-- Generated on " . date('Y-m-d H:i:s') . "\n\n";
            $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tablesResult as $tableRow) {
                $tableName = $tableRow->$tablesKey;

                // Dapatkan struktur tabel
                $createTableResult = \DB::select("SHOW CREATE TABLE `{$tableName}`");
                $createTableKey = 'Create Table';
                $sqlDump .= "-- Table structure for `{$tableName}`\n";
                $sqlDump .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sqlDump .= $createTableResult[0]->$createTableKey . ";\n\n";

                // Dapatkan data tabel
                $rows = \DB::select("SELECT * FROM `{$tableName}`");
                if (count($rows) > 0) {
                    $sqlDump .= "-- Dumping data for `{$tableName}`\n";
                    foreach ($rows as $row) {
                        $rowArray = (array)$row;
                        $columns = array_keys($rowArray);
                        $escapedColumns = array_map(fn($col) => "`{$col}`", $columns);

                        $values = array_values($rowArray);
                        $escapedValues = array_map(function($val) {
                            if (is_null($val)) return 'NULL';
                            return "'" . addslashes($val) . "'";
                        }, $values);

                        $sqlDump .= "INSERT INTO `{$tableName}` (" . implode(', ', $escapedColumns) . ") VALUES (" . implode(', ', $escapedValues) . ");\n";
                    }
                    $sqlDump .= "\n";
                }
            }

            $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";

            $filename = "backup_" . date('Y-m-d_H-i-s') . ".sql";
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $filePath = "{$tempDir}/{$filename}";
            file_put_contents($filePath, $sqlDump);

            ActivityLog::log('Backup Data', 'Melakukan backup database sistem.');

            return response()->download($filePath, $filename)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mencadangkan data: ' . $e->getMessage());
        }
    }
}
