<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeFullController extends Command
{
    protected $signature = 'make:fullcontroller {name}';
    protected $description = 'Membuat folder controller & routes sekaligus untuk resource & API. Nama folder harus diawali huruf besar.';

    public function handle()
    {
        $name = $this->argument('name');

        // === Validasi huruf besar di awal ===
        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $this->error("Nama folder harus diawali huruf besar dan hanya mengandung huruf/angka. Contoh: Packaging");
            return 1;
        }

        // === 1. Buat folder controller ===
        $controllerFolder = app_path("Http/Controllers/{$name}");
        if (!is_dir($controllerFolder)) mkdir($controllerFolder, 0755, true);

        // Buat controller
        $this->call('make:controller', ['name' => "{$name}/{$name}Controller", '-r' => true]);
        $this->call('make:controller', ['name' => "{$name}/{$name}Api", '--api' => true]);

        $this->info("Controllers berhasil dibuat di {$controllerFolder}");

        // === 2. Buat folder routes ===
        $routesFolder = base_path("routes/{$name}");
        if (!is_dir($routesFolder)) mkdir($routesFolder, 0755, true);

        // Buat file routes
        $webRoutesFile = $routesFolder . "/{$name}Web.php";
        $apiRoutesFile = $routesFolder . "/{$name}Api.php";

        // Isi default routes resource
        if (!file_exists($webRoutesFile)) {
            File::put($webRoutesFile, "<?php\n\nuse Illuminate\Support\Facades\Route;\nuse App\Http\Controllers\\{$name}\\{$name}Controller;\n\nRoute::resource('" . strtolower($name) . "', {$name}Controller::class);\n");
        }

        // Isi default API routes
        if (!file_exists($apiRoutesFile)) {
            File::put($apiRoutesFile, "<?php\n\nuse Illuminate\Support\Facades\Route;\nuse App\Http\Controllers\\{$name}\\{$name}Api;\n\nRoute::prefix('" . strtolower($name) . "')->group(function() {\n    Route::apiResource('" . strtolower($name) . "', {$name}Api::class);\n});\n");
        }

        // === 3. Tambahkan require otomatis ke routes utama ===
        $this->appendRequireToMainRoutes('web.php', $name, $webRoutesFile);
        $this->appendRequireToMainRoutes('api.php', $name, $apiRoutesFile);

        $this->info("Routes berhasil dibuat di {$routesFolder}");
        $this->info("✅ Selesai! Controller & routes lengkap siap digunakan.");
    }

    /**
     * Tambahkan require ke file routes utama (web.php atau api.php)
     */
    protected function appendRequireToMainRoutes(string $mainRouteFile, string $name, string $routeFilePath)
    {
        $mainFile = base_path("routes/{$mainRouteFile}");
        $requireLine = "require base_path('routes/{$name}/" . basename($routeFilePath) . "');";

        // Pastikan file utama ada
        if (!file_exists($mainFile)) {
            $this->error("File routes/{$mainRouteFile} tidak ditemukan.");
            return;
        }

        // Cek apakah sudah ada
        $content = File::get($mainFile);
        if (strpos($content, $requireLine) !== false) {
            $this->line("⚠️  Baris require sudah ada di routes/{$mainRouteFile}, dilewati.");
            return;
        }

        // Tambahkan baris require di akhir file
        File::append($mainFile, "\n" . $requireLine . "\n");
        $this->info("✅ Require ditambahkan ke routes/{$mainRouteFile}");
    }
}
