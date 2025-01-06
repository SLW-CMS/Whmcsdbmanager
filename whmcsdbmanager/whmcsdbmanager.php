<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("Bu dosyaya doğrudan erişemezsiniz");
}

/**
 * 1) Eklenti Temel Bilgileri (Config)
 */
function whmcsdbmanager_config()
{
    return [
        'name'        => 'Whmcs Dbmanager',
        'description' => 'WHMCS Log Temizleme ve Optimize Paneli (Capsule Entegrasyonlu)',
        'version'     => '1.0',
        'author'      => 'Ali Çömez (Slaweally)',
        'fields'      => [
            // Gerekirse ek ayarlar tanımlayabilirsiniz
        ],
    ];
}

/**
 * 2) Eklenti Aktifleştirme
 */
function whmcsdbmanager_activate()
{
    try {
        // Örnek: Gerekliyse tablolar oluşturabilirsiniz
        // Capsule::schema()->create(...);

        return [
            'status' => 'success',
            'description' => 'Whmcs Dbmanager eklentisi başarıyla aktifleştirildi.',
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Aktifleştirme esnasında hata oluştu: ' . $e->getMessage(),
        ];
    }
}

/**
 * 3) Eklenti Pasifleştirme
 */
function whmcsdbmanager_deactivate()
{
    try {
        // Örnek: pasifleştirirken tabloları silebilirsiniz
        // Capsule::schema()->dropIfExists(...);

        return [
            'status' => 'success',
            'description' => 'Whmcs Dbmanager eklentisi başarıyla pasifleştirildi.',
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Pasifleştirme esnasında hata oluştu: ' . $e->getMessage(),
        ];
    }
}

/**
 * 4) Eklenti Yükseltme (Upgrade)
 */
function whmcsdbmanager_upgrade($vars)
{
    // Örnek: Sürüm kontrolü yapıp yeni alanlar ekleyebilirsiniz
    $version = $vars['version'];
    // if ($version < 1.1) { ... }
}

/**
 * 5) Addon Çıktısı (Admin Panelinde Gösterilen Kısım)
 */
function whmcsdbmanager_output($vars)
{
    // A) Genel değişkenler
    $dbStatus        = true;
    $errorMsg        = '';
    $operationResult = '';

    // Sayfalama
    $limitsArray   = [50, 100, 300, 500];
    $selectedLimit = (isset($_REQUEST['limit']) && in_array($_REQUEST['limit'], $limitsArray))
        ? (int)$_REQUEST['limit']
        : 50;
    $page   = isset($_REQUEST['page']) ? max(1, (int)$_REQUEST['page']) : 1;
    $offset = ($page - 1) * $selectedLimit;

    // Varsayılan temizlik önerisi tabloları
    $defaultCleanupTables = [
        'tblactivitylog',
        'tblmodulelog',
        'tblwhoislog',
        'tbladminlog',
        'tblgatewaylog',
        'tblticketlog',
        'tblioncube_file_log',
        'tblupdatelog'
    ];

    // Tablo listesi
    $tables     = [];
    $tableCount = 0;
    $dbSize     = 0.0;

    // B) SHOW TABLE STATUS ile tablo bilgilerini çek
    try {
        $allTables = Capsule::select("SHOW TABLE STATUS");
        $tableCount = count($allTables);

        // Veritabanı toplam boyutu
        $dataLengths = [];
        foreach ($allTables as $tbl) {
            // Bazı MySQL versiyonlarında "Data_length" büyük/küçük harf farkı olabilir
            $dataLengths[] = $tbl->Data_length;
        }
        $dbSize = array_sum($dataLengths) / 1024 / 1024; // MB

        // $allTables bir dizi stdClass. array_slice kullanabilmek için array'e dönüştürelim
        $allTablesArray = json_decode(json_encode($allTables), true);
        $tables = array_slice($allTablesArray, $offset, $selectedLimit);

    } catch (\Exception $e) {
        $dbStatus = false;
        $errorMsg = $e->getMessage();
    }

    // C) POST (Toplu İşlemler, Yeni Tablo Oluşturma, Full Backup)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbStatus) {
        $action         = $_POST['action']         ?? '';
        $selectedTables = $_POST['tables']         ?? [];
        $newTableName   = trim($_POST['new_table_name'] ?? '');

        try {
            // -- 1) Toplu İşlemler
            if (in_array($action, ['export', 'clean', 'drop', 'optimize'])) {
                if (!empty($selectedTables)) {
                    foreach ($selectedTables as $tableName) {
                        switch ($action) {
                            case 'clean':
                                Capsule::statement("TRUNCATE TABLE `$tableName`");
                                $operationResult .= "`$tableName` başarıyla temizlendi.\n";
                                break;
                            case 'drop':
                                Capsule::statement("DROP TABLE `$tableName`");
                                $operationResult .= "`$tableName` başarıyla kaldırıldı.\n";
                                break;
                            case 'export':
                                $results = Capsule::select("SELECT * FROM `$tableName`");
                                $json    = json_encode($results);
                                file_put_contents("export_{$tableName}.json", $json);
                                $operationResult .= "`$tableName` başarıyla dışarı aktarıldı (export_{$tableName}.json).\n";
                                break;
                            case 'optimize':
                                Capsule::statement("OPTIMIZE TABLE `$tableName`");
                                $operationResult .= "`$tableName` optimize edildi.\n";
                                break;
                        }
                    }
                } else {
                    $operationResult .= "Lütfen işlem yapmak için en az bir tablo seçin.\n";
                }
            }

            // -- 2) Yeni Tablo Oluşturma
            elseif ($action === 'create') {
                if (!empty($newTableName)) {
                    Capsule::statement("CREATE TABLE `$newTableName` (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        data TEXT
                    ) ENGINE=InnoDB CHARSET=utf8mb4");
                    $operationResult .= "`$newTableName` başarıyla oluşturuldu.\n";
                } else {
                    $operationResult .= "Tablo adı boş olamaz.\n";
                }
            }

            // -- 3) Full Backup (mysqldump)
           elseif ($action === 'fullbackup') {

				// exec() kontrolü
				if (!function_exists('exec')) {
					// exec devre dışı, hata döndürmek yerine kullanıcıya bildirelim
					$operationResult .= "Sunucunuzda exec() fonksiyonu devre dışı bırakılmış. Tam yedek alma işlemi yapılamıyor.\n";
				} else {
					// Normal mysqldump işlemleri
					$dbName = Capsule::connection()->getDatabaseName();
					$dbHost = Capsule::connection()->getConfig('host');
					$dbUser = Capsule::connection()->getConfig('username');
					$dbPass = Capsule::connection()->getConfig('password');

					$filename = 'full_backup_' . date('Y-m-d_H-i-s') . '.sql';
					$command  = "mysqldump --host=\"{$dbHost}\" --user=\"{$dbUser}\" --password=\"{$dbPass}\" \"{$dbName}\" > \"{$filename}\"";

					exec($command, $output, $status);
					if ($status === 0) {
						$operationResult .= "Tüm veritabanı yedeği başarıyla alındı: <strong>{$filename}</strong>\n";
					} else {
						$operationResult .= "Yedek alınırken bir hata oluştu. (Çıkış kodu: {$status})\n";
					}
				}
			}

        } catch (\Exception $e) {
            $operationResult .= "Hata oluştu: " . $e->getMessage() . "\n";
        }
    }

    // D) HTML ÇIKTISI
    echo '<div style="margin:15px;">';
    
    // Başlık
    echo '<h2>Whmcs Dbmanager</h2>';

    // Veritabanı bağlantı hatası
    if (!$dbStatus) {
        echo '<div class="alert alert-danger" role="alert">';
        echo 'Veritabanı bağlantısı başarısız: ' . htmlspecialchars($errorMsg);
        echo '</div>';
        echo '</div>'; // container
        return;
    }

    // Uyarı + Spoiler (Nasıl Kullanılır?)
    echo '
    <div class="alert alert-warning" role="alert">
        <strong>Uyarı:</strong> Bu araç yalnızca profesyoneller içindir. Yanlış işlem geri dönüşü olmayan sorunlara yol açabilir.
    </div>
    
    <!-- Kullanım Rehberi (Spoiler/Collapse) -->
    <div class="alert alert-info" role="alert">
        <strong>Nasıl Kullanılır?</strong> 
        <button class="btn btn-sm btn-link" type="button" data-toggle="collapse" data-target="#usageDetails" aria-expanded="false" aria-controls="usageDetails">
            Detayları Göster
        </button>

        <div class="collapse mt-3" id="usageDetails">
            <div class="card card-body">
                <p>Bu panel sayesinde WHMCS veritabanınızda yer alan tabloları temizleyebilir, silebilir (drop), optimize edebilir, export (JSON) alabilir ve yeni tablo oluşturabilirsiniz.</p>
                <ul>
                    <li><strong>Varsayılan Temizlik Önerisi:</strong> Sıklıkla log kaydı biriken tabloları kısa sürede temizlemenizi sağlar.</li>
                    <li><strong>Toplu İşlemler:</strong> PhpMyAdmin benzeri şekilde birden fazla tabloyu seçip tek tıkla temizle (truncate), sil (drop), optimize vb. işlemler yapabilirsiniz.</li>
                    <li><strong>Yeni Tablo Oluştur:</strong> Basit bir tablo oluşturmak isterseniz kullanabilirsiniz.</li>
                    <li><strong>Full Yedek Al:</strong> Mevcut WHMCS veritabanının tamamının <em>.sql</em> uzantılı yedeğini alır. Sunucunuzda <code>mysqldump</code> komutu çalışabilir olmalıdır.</li>
                </ul>
                <p>İşlem öncesi onay isteyen ek bir modal açılır, böylece yanlışlıkla tablo silme veya boşaltma riskini azaltmış olursunuz.</p>
            </div>
        </div>
    </div>
    ';

    // Operasyon sonuç mesajı
    if (!empty($operationResult)) {
        echo '<div class="alert alert-info" role="alert">';
        echo nl2br(htmlspecialchars($operationResult));
        echo '</div>';
    }

    // Veritabanı Bilgisi
    echo '
    <div class="d-flex justify-content-between align-items-center mb-3" style="display:flex; justify-content:space-between;">
        <div>
            <h5>Veritabanı Toplam Boyutu: '. round($dbSize, 2) .' MB</h5>
            <p>Toplam Tablolar: '. $tableCount .'</p>
        </div>

        <div>
            <!-- Varsayılan Temizlik Önerisi Butonu (Modal Tetikleyici) -->
            <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#defaultTablesModal">
                Varsayılan Temizlik Önerisi
            </button>

            <!-- Tüm Yedek Alma (Full Backup) Butonu - Ayrı bir modal ile onay alacağız -->
            <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#fullBackupModal">
                Tam Yedek Al
            </button>
        </div>
    </div>
    ';

    // Modal: Varsayılan Temizlik Önerisi Tabloları
    echo '
    <div class="modal fade" id="defaultTablesModal" tabindex="-1" role="dialog" aria-labelledby="defaultTablesModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="defaultTablesModalLabel">Varsayılan Temizlik Önerilen Tablolar</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Kapat">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <form method="POST" id="defaultCleanupForm">
                <input type="hidden" name="action" value="clean">';
                foreach ($defaultCleanupTables as $table) {
                    $tableSafe = htmlspecialchars($table);
                    echo '
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="tables[]" value="'. $tableSafe .'" id="default_'. $tableSafe .'">
                        <label class="form-check-label" for="default_'. $tableSafe .'">
                            '. $tableSafe .'
                        </label>
                    </div>';
                }
    echo '
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Kapat</button>
            <button type="submit" form="defaultCleanupForm" class="btn btn-danger">Seçilenleri Temizle</button>
          </div>
        </div>
      </div>
    </div>
    ';

    // Modal: Full Backup Onayı
    echo '
    <div class="modal fade" id="fullBackupModal" tabindex="-1" role="dialog" aria-labelledby="fullBackupModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <form method="POST">
            <input type="hidden" name="action" value="fullbackup">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="fullBackupModalLabel">Tam Yedek Al</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Kapat">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <div class="modal-body">
                <p>Veritabanının tamamının <strong>.sql</strong> yedeğini almak üzeresiniz. Sunucunuzda <code>mysqldump</code> komutunun çalışabilir olması gerekir.</p>
                <p><strong>Devam etmek istediğinize emin misiniz?</strong></p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Vazgeç</button>
                <button type="submit" class="btn btn-primary">Evet, Yedek Al</button>
              </div>
            </div>
        </form>
      </div>
    </div>
    ';

    // Yeni Tablo Oluşturma
    echo '
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Yeni Tablo Oluştur</h5>
            <form method="POST" class="row g-3">
                <div class="col-auto">
                    <label for="new_table_name" class="visually-hidden">Tablo Adı</label>
                    <input type="text" class="form-control" id="new_table_name" name="new_table_name" placeholder="Yeni tablo adı girin">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Oluştur</button>
                    <input type="hidden" name="action" value="create">
                </div>
            </form>
        </div>
    </div>
    ';

    // Tablo Listesi (Sayfalandırma)
    echo '
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Tablo Listesi</span>
            <form method="GET" class="d-flex align-items-center">
                <label for="limitSelect" class="mr-2 mb-0">Gösterilecek kayıt sayısı:</label>
                <select name="limit" id="limitSelect" class="form-control" style="width:auto;" onchange="this.form.submit()">';
                foreach ($limitsArray as $lim) {
                    $selected = ($lim == $selectedLimit) ? 'selected' : '';
                    echo '<option value="'.$lim.'" '.$selected.'>'.$lim.'</option>';
                }
    echo '      </select>
                <input type="hidden" name="module" value="whmcsdbmanager">
                <input type="hidden" name="page" value="'.$page.'">
            </form>
        </div>
        <div class="card-body">
    ';

    if (empty($tables)) {
        echo '<div class="alert alert-warning">Hiç tablo bulunamadı.</div>';
    } else {
        echo '
        <form method="POST" id="bulkActionsForm">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="selectAll"></th>
                            <th>Tablo Adı</th>
                            <th>Boyut (KB)</th>
                            <th>Kayıt Sayısı</th>
                        </tr>
                    </thead>
                    <tbody>
        ';
        foreach ($tables as $tableInfo) {
            $tableName  = $tableInfo['Name'];
            $dataLength = $tableInfo['Data_length'];

            // Kayıt sayısı
            $count = 0;
            try {
                $row = Capsule::selectOne("SELECT COUNT(*) as totalCount FROM `$tableName`");
                $count = $row->totalCount;
            } catch (\Exception $ex) {
                // Bazı tablolarda yetki veya farklı nedenlerle hata olabilir
            }

            $safeName    = htmlspecialchars($tableName);
            $tableSizeKB = round($dataLength / 1024, 2);
            echo '
            <tr>
                <td><input type="checkbox" name="tables[]" value="'.$safeName.'"></td>
                <td>'.$safeName.'</td>
                <td>'.$tableSizeKB.'</td>
                <td>'.$count.'</td>
            </tr>';
        }
        echo '
                    </tbody>
                </table>
            </div>

            <!-- Toplu İşlemler Butonları -->
            <div class="mt-3">
                <input type="hidden" name="action" value="" id="bulkActionInput">
                <button type="button" class="btn btn-info"    onclick="setBulkAction(\'export\')">Seçilenleri Dışarı Aktar</button>
                <button type="button" class="btn btn-warning" onclick="setBulkAction(\'clean\')">Seçilenleri Boşalt (Truncate)</button>
                <button type="button" class="btn btn-danger"  onclick="setBulkAction(\'drop\')">Seçilenleri Kaldır (Drop)</button>
                <button type="button" class="btn btn-success" onclick="setBulkAction(\'optimize\')">Seçilenleri Optimize Et</button>
            </div>
        </form>
        ';
    }
    echo '
        </div> <!-- card-body -->
    </div> <!-- card -->
    ';

    // Sayfalandırma
    $totalPages = ($tableCount > 0) ? ceil($tableCount / $selectedLimit) : 1;
    if ($totalPages > 1) {
        echo '<nav class="mt-3"><ul class="pagination">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i == $page) ? 'active' : '';
            echo '
            <li class="page-item '.$active.'">
                <a class="page-link" href="?module=whmcsdbmanager&page='.$i.'&limit='.$selectedLimit.'">'.$i.'</a>
            </li>';
        }
        echo '</ul></nav>';
    }

    // İşlem Onay Modal (Toplu İşlemler için)
    echo '
    <!-- İşlem Onay Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog" aria-labelledby="confirmationModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="confirmationModalLabel">İşlem Onayı</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Kapat">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p><strong><span id="confirmationActionName"></span></strong> işlemini gerçekleştirmek üzeresiniz. Emin misiniz?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Hayır</button>
            <button type="button" class="btn btn-primary" id="confirmYesBtn">Evet</button>
          </div>
        </div>
      </div>
    </div>
    ';

    echo '</div>'; // container kapanış

    // JS Kodları
    echo "
    <script>
        // Tüm tabloları seç/kaldır
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name=\"tables[]\"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        var pendingAction = null;

        // setBulkAction: işlem seçildiğinde Confirmation Modal aç
        function setBulkAction(action) {
            pendingAction = action; // İşlemi global değişkende tut
            // Modal mesajında hangi işlemi yapıyoruz, yazalım
            let actionMap = {
                'export': 'Dışarı Aktarma',
                'clean': 'Temizleme (Truncate)',
                'drop': 'Silme (Drop)',
                'optimize': 'Optimize'
            };
            let actionText = actionMap[action] ? actionMap[action] : action;
            document.getElementById('confirmationActionName').textContent = actionText;

            // Modal'ı aç
            window.jQuery('#confirmationModal').modal('show');
        }

        // Evet butonuna basıldığında formu submit et
        document.getElementById('confirmYesBtn')?.addEventListener('click', function() {
            if (pendingAction) {
                document.getElementById('bulkActionInput').value = pendingAction;
                document.getElementById('bulkActionsForm').submit();
            }
        });
    </script>
    ";
}
