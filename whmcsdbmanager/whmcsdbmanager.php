<?php

// Enable error reporting (use during development, disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("You cannot access this file directly");
}

/**
 * 1) Addon Configuration
 */
function whmcsdbmanager_config()
{
    return [
        'name' => 'WHMCS Dbmanager',
        'description' => 'WHMCS Log Cleaning and Optimization Panel',
        'version' => '1.1',
        'author' => 'Ali Çömez (Slaweally)',
        'fields' => [
            // Define additional settings if necessary
        ],
    ];
}

/**
 * 2) Addon Activation
 */
function whmcsdbmanager_activate()
{
    try {
        // Check required PHP extensions
        $requiredExtensions = ['pdo_mysql', 'json', 'mbstring'];
        $missingExtensions = [];

        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }

        if (!empty($missingExtensions)) {
            throw new \Exception('Missing PHP extensions: ' . implode(', ', $missingExtensions));
        }

        // Version control
        $currentVersion = Capsule::table('tbladdonmodules')
            ->where('module', 'whmcsdbmanager')
            ->where('setting', 'version')
            ->value('value');

        if ($currentVersion === null) {
            // Initial installation
            Capsule::schema()->create('tblwhmcsdbmanager_logs', function ($table) {
                $table->increments('id');
                $table->string('action');
                $table->text('description');
                $table->timestamp('created_at')->useCurrent();
            });

            // Insert initial data
            Capsule::table('tblwhmcsdbmanager_logs')->insert([
                'action' => 'activate',
                'description' => 'WHMCS Dbmanager addon has been activated.',
            ]);

            // Insert default settings
            Capsule::table('tbladdonmodules')->insert([
                'module' => 'whmcsdbmanager',
                'setting' => 'default_cleanup_limit',
                'value' => '50',
            ]);

            // Save version
            Capsule::table('tbladdonmodules')->insert([
                'module' => 'whmcsdbmanager',
                'setting' => 'version',
                'value' => '1.0',
            ]);
        } else {
            // Already activated, perform updates if necessary
            if (version_compare($currentVersion, '1.1', '<')) {
                // For example, add a new table or modify existing tables
                // Capsule::schema()->table('tblwhmcsdbmanager_logs', function ($table) {
                //     $table->string('new_field')->nullable();
                // });

                // Update version
                Capsule::table('tbladdonmodules')
                    ->where('module', 'whmcsdbmanager')
                    ->where('setting', 'version')
                    ->update(['value' => '1.1']);
            }
        }

        return [
            'status' => 'success',
            'description' => 'WHMCS Dbmanager addon has been successfully activated.',
        ];
    } catch (\Exception $e) {
        // Log errors to WHMCS logs
        logModuleCall(
            'whmcsdbmanager',
            'activate',
            [],
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return [
            'status' => 'error',
            'description' => 'An error occurred during activation: ' . $e->getMessage(),
        ];
    }
}

/**
 * 3) Addon Deactivation
 */
function whmcsdbmanager_deactivate()
{
    try {
        // Example: You can drop tables upon deactivation
        // Capsule::schema()->dropIfExists('tblwhmcsdbmanager_logs');

        return [
            'status' => 'success',
            'description' => 'WHMCS Dbmanager addon has been successfully deactivated.',
        ];
    } catch (\Exception $e) {
        // Log errors to WHMCS logs
        logModuleCall(
            'whmcsdbmanager',
            'deactivate',
            [],
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return [
            'status' => 'error',
            'description' => 'An error occurred during deactivation: ' . $e->getMessage(),
        ];
    }
}

/**
 * 4) Addon Upgrade
 */
function whmcsdbmanager_upgrade($vars)
{
    try {
        // Perform version checks and necessary updates
        $version = $vars['version'];
        // Example:
        // if (version_compare($version, '1.1', '<')) {
        //     Capsule::schema()->table('tblwhmcsdbmanager_logs', function ($table) {
        //         $table->string('new_field')->nullable();
        //     });
        // }

        return [
            'status' => 'success',
            'description' => 'WHMCS Dbmanager addon has been successfully upgraded.',
        ];
    } catch (\Exception $e) {
        // Log errors to WHMCS logs
        logModuleCall(
            'whmcsdbmanager',
            'upgrade',
            [],
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return [
            'status' => 'error',
            'description' => 'An error occurred during upgrade: ' . $e->getMessage(),
        ];
    }
}

/**
 * 5) Addon Output (Displayed in Admin Panel)
 */
function whmcsdbmanager_output($vars)
{
    // A) General variables
    $dbStatus        = true;
    $errorMsg        = '';
    $operationResult = '';

    // New parameters: current_table or edit action
    $currentTable = isset($_GET['current_table']) ? trim($_GET['current_table']) : '';
    $editAction   = isset($_GET['action']) && $_GET['action'] === 'edit';
    $editTable    = isset($_GET['table']) ? trim($_GET['table']) : '';
    $editId       = isset($_GET['id']) ? trim($_GET['id']) : '';

    // Check database connection
    try {
        Capsule::connection()->getPdo();
    } catch (\Exception $e) {
        $dbStatus = false;
        $errorMsg = $e->getMessage();
    }

    if (!$dbStatus) {
        echo '<div class="alert alert-danger" role="alert">';
        echo 'Database connection failed: ' . htmlspecialchars($errorMsg);
        echo '</div>';
        return;
    }

    // If edit action, display edit form
    if ($editAction && !empty($editTable) && !empty($editId)) {
        display_edit_form($editTable, $editId, $operationResult, $errorMsg);
        return;
    }

    // If current_table is set, display table data
    if (!empty($currentTable)) {
        display_table_data($currentTable, $operationResult, $errorMsg);
        return;
    }

    // Pagination
    $limitsArray   = [50, 100, 300, 500];
    $selectedLimit = (isset($_REQUEST['limit']) && in_array($_REQUEST['limit'], $limitsArray))
        ? (int)$_REQUEST['limit']
        : 50;
    $page   = isset($_REQUEST['page']) ? max(1, (int)$_REQUEST['page']) : 1;
    $offset = ($page - 1) * $selectedLimit;

    // Default cleanup suggested tables
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

    // Table list
    $tables     = [];
    $tableCount = 0;
    $dbSize     = 0.0;

    // B) Fetch table information using SHOW TABLE STATUS
    try {
        $allTables = Capsule::select("SHOW TABLE STATUS");
        $tableCount = count($allTables);

        // Total database size
        $dataLengths = [];
        foreach ($allTables as $tbl) {
            // Some MySQL versions may have different cases for "Data_length"
            $dataLengths[] = $tbl->Data_length;
        }
        $dbSize = array_sum($dataLengths) / 1024 / 1024; // MB

        // Convert stdClass array to associative array for array_slice
        $allTablesArray = json_decode(json_encode($allTables), true);
        $tables = array_slice($allTablesArray, $offset, $selectedLimit);

    } catch (\Exception $e) {
        $dbStatus = false;
        $errorMsg = $e->getMessage();
    }

    // C) POST (Bulk Operations, Create New Table, Full Backup)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbStatus) {
        $action         = $_POST['action']         ?? '';
        $selectedTables = $_POST['tables']         ?? [];
        $newTableName   = trim($_POST['new_table_name'] ?? '');

        try {
            // -- 1) Bulk Operations
            if (in_array($action, ['export', 'clean', 'drop', 'optimize'])) {
                if (!empty($selectedTables)) {
                    foreach ($selectedTables as $tableName) {
                        switch ($action) {
                            case 'clean':
                                Capsule::statement("TRUNCATE TABLE `$tableName`");
                                $operationResult .= "`$tableName` has been successfully cleaned.<br>";
                                break;
                            case 'drop':
                                Capsule::statement("DROP TABLE `$tableName`");
                                $operationResult .= "`$tableName` has been successfully dropped.<br>";
                                break;
                            case 'export':
                                $results = Capsule::select("SELECT * FROM `$tableName`");
                                $json    = json_encode($results, JSON_PRETTY_PRINT);
                                $exportFile = 'export_' . $tableName . '_' . date('Y-m-d_H-i-s') . '.json';
                                if (file_put_contents($exportFile, $json) !== false) {
                                    $operationResult .= "`$tableName` has been successfully exported ($exportFile).<br>";
                                } else {
                                    $operationResult .= "Failed to export `$tableName`.<br>";
                                }
                                break;
                            case 'optimize':
                                Capsule::statement("OPTIMIZE TABLE `$tableName`");
                                $operationResult .= "`$tableName` has been optimized.<br>";
                                break;
                        }
                    }
                } else {
                    $operationResult .= "Please select at least one table to perform the action.<br>";
                }
            }

            // -- 2) Create New Table
            elseif ($action === 'create') {
                if (!empty($newTableName)) {
                    // Validate table name (only letters, numbers, and underscores)
                    if (preg_match('/^[A-Za-z0-9_]+$/', $newTableName)) {
                        Capsule::statement("CREATE TABLE `$newTableName` (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            data TEXT
                        ) ENGINE=InnoDB CHARSET=utf8mb4");
                        $operationResult .= "`$newTableName` has been successfully created.<br>";
                    } else {
                        $operationResult .= "Invalid table name. Only letters, numbers, and underscores are allowed.<br>";
                    }
                } else {
                    $operationResult .= "Table name cannot be empty.<br>";
                }
            }

            // -- 3) Full Backup (mysqldump)
            elseif ($action === 'fullbackup') {

                // Check if exec() is enabled
                if (!function_exists('exec')) {
                    // exec is disabled, notify the user instead of throwing an error
                    $operationResult .= "The exec() function is disabled on your server. Full backup cannot be performed.<br>";
                } else {
                    // Normal mysqldump operations
                    $dbName = Capsule::connection()->getDatabaseName();
                    $dbHost = Capsule::connection()->getConfig('host');
                    $dbUser = Capsule::connection()->getConfig('username');
                    $dbPass = Capsule::connection()->getConfig('password');

                    $filename = 'full_backup_' . date('Y-m-d_H-i-s') . '.sql';
                    $command  = "mysqldump --host=\"{$dbHost}\" --user=\"{$dbUser}\" --password=\"{$dbPass}\" \"{$dbName}\" > \"{$filename}\"";

                    exec($command, $output, $status);
                    if ($status === 0) {
                        $operationResult .= "Full database backup has been successfully created: <strong>{$filename}</strong><br>";
                    } else {
                        $operationResult .= "An error occurred while creating the backup. (Exit code: {$status})<br>";
                    }
                }
            }

        } catch (\Exception $e) {
            $operationResult .= "An error occurred: " . $e->getMessage() . "<br>";
        }
    }

    // D) HTML Output
    echo '<div style="margin:15px;">';
    
    // Title
    echo '<h2>WHMCS Dbmanager</h2>';

    // Warning + Spoiler (How to Use?)
    echo '
    <div class="alert alert-warning" role="alert">
        <strong>Warning:</strong> This tool is for professionals only. Incorrect operations can lead to irreversible issues.
    </div>
    
    <!-- Usage Guide (Spoiler/Collapse) -->
    <div class="alert alert-info" role="alert">
        <strong>How to Use:</strong> 
        <button class="btn btn-sm btn-link" type="button" data-toggle="collapse" data-target="#usageDetails" aria-expanded="false" aria-controls="usageDetails">
            Show Details
        </button>

        <div class="collapse mt-3" id="usageDetails">
            <div class="card card-body">
                <p>With this panel, you can clean, drop, optimize, export (JSON), and create new tables in your WHMCS database.</p>
                <ul>
                    <li><strong>Default Cleanup Suggestion:</strong> Quickly clean tables that frequently accumulate log entries.</li>
                    <li><strong>Bulk Operations:</strong> Similar to PhpMyAdmin, select multiple tables and perform actions like truncate, drop, optimize, etc., with a single click.</li>
                    <li><strong>Create New Table:</strong> Use this if you want to create a simple new table.</li>
                    <li><strong>Full Backup:</strong> Creates a <em>.sql</em> backup of your entire WHMCS database. The <code>mysqldump</code> command must be enabled on your server.</li>
                </ul>
                <p>A confirmation modal appears before performing actions to reduce the risk of accidentally dropping or truncating tables.</p>
            </div>
        </div>
    </div>
    ';

    // Operation result message
    if (!empty($operationResult)) {
        echo '<div class="alert alert-info" role="alert">';
        echo $operationResult;
        echo '</div>';
    }

    // Database Information
    echo '
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5>Total Database Size: '. round($dbSize, 2) .' MB</h5>
            <p>Total Tables: '. $tableCount .'</p>
        </div>

        <div>
            <!-- Default Cleanup Suggestion Button (Modal Trigger) -->
            <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#defaultTablesModal">
                Default Cleanup Suggestion
            </button>

            <!-- Full Backup Button - Will trigger a separate modal for confirmation -->
            <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#fullBackupModal">
                Take Full Backup
            </button>
        </div>
    </div>
    ';

    // Modal: Default Cleanup Suggested Tables
    echo '
    <div class="modal fade" id="defaultTablesModal" tabindex="-1" role="dialog" aria-labelledby="defaultTablesModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="defaultTablesModalLabel">Default Cleanup Suggested Tables</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
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
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" form="defaultCleanupForm" class="btn btn-danger">Clean Selected</button>
          </div>
        </div>
      </div>
    </div>
    ';

    // Modal: Full Backup Confirmation
    echo '
    <div class="modal fade" id="fullBackupModal" tabindex="-1" role="dialog" aria-labelledby="fullBackupModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <form method="POST">
            <input type="hidden" name="action" value="fullbackup">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="fullBackupModalLabel">Take Full Backup</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <div class="modal-body">
                <p>You are about to create a <strong>.sql</strong> backup of your entire database. The <code>mysqldump</code> command must be enabled on your server.</p>
                <p><strong>Are you sure you want to proceed?</strong></p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Yes, Backup</button>
              </div>
            </div>
        </form>
      </div>
    </div>
    ';

    // Create New Table
    echo '
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Create New Table</h5>
            <form method="POST" class="row g-3">
                <div class="col-auto">
                    <label for="new_table_name" class="visually-hidden">Table Name</label>
                    <input type="text" class="form-control" id="new_table_name" name="new_table_name" placeholder="Enter new table name" required>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Create</button>
                    <input type="hidden" name="action" value="create">
                </div>
            </form>
        </div>
    </div>
    ';

    // Table List (Pagination)
    echo '
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Table List</span>
            <form method="GET" class="d-flex align-items-center">
                <label for="limitSelect" class="mr-2 mb-0">Number of records to display:</label>
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
        echo '<div class="alert alert-warning">No tables found.</div>';
    } else {
        echo '
        <form method="POST" id="bulkActionsForm">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="selectAll"></th>
                            <th>Table Name</th>
                            <th>Size (KB)</th>
                            <th>Record Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        ';
        foreach ($tables as $tableInfo) {
            $tableName  = $tableInfo['Name'];
            $dataLength = $tableInfo['Data_length'];

            // Record count
            $count = 0;
            try {
                $row = Capsule::selectOne("SELECT COUNT(*) as totalCount FROM `$tableName`");
                $count = $row->totalCount;
            } catch (\Exception $ex) {
                // Some tables may have permission issues or other errors
            }

            $safeName    = htmlspecialchars($tableName);
            $tableSizeKB = round($dataLength / 1024, 2);
            echo '
            <tr>
                <td><input type="checkbox" name="tables[]" value="'.$safeName.'"></td>
                <td>'.$safeName.'</td>
                <td>'.$tableSizeKB.'</td>
                <td>'.$count.'</td>
                <td>
                    <a href="?module=whmcsdbmanager&action=edit&table='.urlencode($tableName).'&id=" class="btn btn-sm btn-primary disabled">Edit</a>
                    <a href="?module=whmcsdbmanager&current_table='.urlencode($tableName).'" class="btn btn-sm btn-info">View Data</a>
                </td>
            </tr>';
        }
        echo '
                    </tbody>
                </table>
            </div>

            <!-- Bulk Actions Buttons -->
            <div class="mt-3">
                <input type="hidden" name="action" value="" id="bulkActionInput">
                <button type="button" class="btn btn-info"    onclick="setBulkAction(\'export\')">Export Selected</button>
                <button type="button" class="btn btn-warning" onclick="setBulkAction(\'clean\')">Truncate Selected</button>
                <button type="button" class="btn btn-danger"  onclick="setBulkAction(\'drop\')">Drop Selected</button>
                <button type="button" class="btn btn-success" onclick="setBulkAction(\'optimize\')">Optimize Selected</button>
            </div>
        </form>
        ';
    }
    echo '
        </div> <!-- card-body -->
    </div> <!-- card -->
    ';

    // Pagination
    $totalPages = ($tableCount > 0) ? ceil($tableCount / $selectedLimit) : 1;
    if ($totalPages > 1) {
        echo '<nav class="mt-3"><ul class="pagination">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i == $page) ? 'active' : '';
            echo '
            <li class="page-item ' . $active . '">
                <a class="page-link" href="?module=whmcsdbmanager&page='.$i.'&limit='.$selectedLimit.'">'.$i.'</a>
            </li>';
        }
        echo '</ul></nav>';
    }

    // Operation Confirmation Modal (for Bulk Actions)
    echo '
    <!-- Operation Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog" aria-labelledby="confirmationModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="confirmationModalLabel">Confirm Action</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p><strong><span id="confirmationActionName"></span></strong> operation is about to be performed. Are you sure?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">No</button>
            <button type="button" class="btn btn-primary" id="confirmYesBtn">Yes</button>
          </div>
        </div>
      </div>
    </div>
    ';

    echo '</div>'; // container closing

    // JS Scripts
    echo "
    <script>
        // Select/Deselect all tables and rows
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name=\"tables[]\"], input[name=\"delete_rows[]\"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        var pendingAction = null;

        // setBulkAction: Open Confirmation Modal when an action is selected
        function setBulkAction(action) {
            pendingAction = action; // Store action in a global variable
            // Set the action name in the modal message
            let actionMap = {
                'export': 'Export',
                'clean': 'Truncate',
                'drop': 'Drop',
                'optimize': 'Optimize'
            };
            let actionText = actionMap[action] ? actionMap[action] : action;
            document.getElementById('confirmationActionName').textContent = actionText;

            // Open the modal
            window.jQuery('#confirmationModal').modal('show');
        }

        // When the Yes button is clicked, submit the form
        document.getElementById('confirmYesBtn')?.addEventListener('click', function() {
            if (pendingAction) {
                document.getElementById('bulkActionInput').value = pendingAction;
                document.getElementById('bulkActionsForm').submit();
            }
        });
    </script>
    ";
}

/**
 * Function to Display and Manage Table Data
 */
function display_table_data($tableName, &$operationResult, &$errorMsg)
{
    // Check database connection
    try {
        Capsule::connection()->getPdo();
        $dbStatus = true;
    } catch (\Exception $e) {
        $dbStatus = false;
        $errorMsg = $e->getMessage();
    }

    if (!$dbStatus) {
        echo '<div class="alert alert-danger" role="alert">';
        echo 'Database connection failed: ' . htmlspecialchars($errorMsg);
        echo '</div>';
        echo '</div>'; // container
        return;
    }

    try {
        // Get table columns
        $columns = Capsule::getSchemaBuilder()->getColumnListing($tableName);
        if (empty($columns)) {
            throw new \Exception("Failed to retrieve table columns.");
        }

        // Pagination
        $limitsArray   = [20, 50, 100];
        $selectedLimit = (isset($_REQUEST['limit']) && in_array($_REQUEST['limit'], $limitsArray))
            ? (int)$_REQUEST['limit']
            : 20;
        $page   = isset($_REQUEST['page']) ? max(1, (int)$_REQUEST['page']) : 1;
        $offset = ($page - 1) * $selectedLimit;

        // Total record count
        $totalRecords = Capsule::table($tableName)->count();

        // Fetch data
        $data = Capsule::table($tableName)->offset($offset)->limit($selectedLimit)->get();

        // C) POST (Delete Row Operation)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_rows'])) {
            $rowsToDelete = $_POST['delete_rows'];
            foreach ($rowsToDelete as $id) {
                // Assume each table has an 'id' column
                if (in_array('id', $columns)) {
                    Capsule::table($tableName)->where('id', $id)->delete();
                    $operationResult .= "ID $id has been successfully deleted.<br>";
                } else {
                    // If 'id' column does not exist, you may need to select an appropriate column
                    $operationResult .= "The table '$tableName' does not have an 'id' column. Delete operation failed.<br>";
                }
            }
        }

        // D) HTML Output
        echo '<div style="margin:15px;">';
        echo '<h2>Table: ' . htmlspecialchars($tableName) . ' - Data Management</h2>';

        // Operation result message
        if (!empty($operationResult)) {
            echo '<div class="alert alert-info" role="alert">';
            echo $operationResult;
            echo '</div>';
        }

        // Back Button
        echo '
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5>Data List</h5>
                <p>Total Records: ' . $totalRecords . '</p>
            </div>
            <div>
                <a href="?module=whmcsdbmanager" class="btn btn-secondary">Go Back</a>
            </div>
        </div>
        ';

        // Table Data
        if (empty($data)) {
            echo '<div class="alert alert-warning">No data found.</div>';
        } else {
            echo '
            <form method="POST" id="rowActionsForm">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th style="width:40px;"><input type="checkbox" id="selectAllRows"></th>';
            foreach ($columns as $column) {
                echo '<th>' . htmlspecialchars($column) . '</th>';
            }
            echo '
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            ';
            foreach ($data as $row) {
                echo '<tr>';
                echo '<td><input type="checkbox" name="delete_rows[]" value="' . htmlspecialchars($row->id) . '"></td>';
                foreach ($columns as $column) {
                    echo '<td>' . htmlspecialchars($row->$column) . '</td>';
                }
                // Enable edit button with necessary attributes
                echo '<td>
                        <a href="?module=whmcsdbmanager&action=edit&table='.urlencode($tableName).'&id='.urlencode($row->id).'" class="btn btn-sm btn-primary">Edit</a>
                      </td>';
                echo '</tr>';
            }
            echo '
                        </tbody>
                    </table>
                </div>

                <!-- Delete Rows Button -->
                <button type="submit" class="btn btn-danger" onclick="return confirm(\'Are you sure you want to delete the selected rows?\')">Delete Selected</button>
            </form>
            ';
        }

        // Pagination
        $totalPages = ($totalRecords > 0) ? ceil($totalRecords / $selectedLimit) : 1;
        if ($totalPages > 1) {
            echo '<nav class="mt-3"><ul class="pagination">';
            for ($i = 1; $i <= $totalPages; $i++) {
                $active = ($i == $page) ? 'active' : '';
                echo '
                <li class="page-item ' . $active . '">
                    <a class="page-link" href="?module=whmcsdbmanager&current_table=' . urlencode($tableName) . '&page=' . $i . '&limit=' . $selectedLimit . '">' . $i . '</a>
                </li>';
            }
            echo '</ul></nav>';
        }

        echo '</div>'; // container closing
    } catch (\Exception $e) {
        echo '<div class="alert alert-danger" role="alert">';
        echo 'Error: ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
}

/**
 * Function to Display Edit Form
 */
function display_edit_form($tableName, $rowId, &$operationResult, &$errorMsg)
{
    // Security Measures: Optional CSRF token can be added

    // Check database connection
    try {
        Capsule::connection()->getPdo();
        $dbStatus = true;
    } catch (\Exception $e) {
        $dbStatus = false;
        $errorMsg = $e->getMessage();
    }

    if (!$dbStatus) {
        echo '<div class="alert alert-danger" role="alert">';
        echo 'Database connection failed: ' . htmlspecialchars($errorMsg);
        echo '</div>';
        echo '</div>'; // container
        return;
    }

    try {
        // Get table columns
        $columns = Capsule::getSchemaBuilder()->getColumnListing($tableName);
        if (empty($columns)) {
            throw new \Exception("Failed to retrieve table columns.");
        }

        // Get row data
        $row = Capsule::table($tableName)->where('id', $rowId)->first();
        if (!$row) {
            throw new \Exception("Row not found.");
        }

        // When form is submitted, update the database
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_row'])) {
            $updateData = [];
            foreach ($columns as $column) {
                if ($column === 'id') continue; // Do not update ID
                if (isset($_POST[$column])) {
                    $updateData[$column] = $_POST[$column];
                }
            }

            Capsule::table($tableName)->where('id', $rowId)->update($updateData);
            $operationResult .= "Row has been successfully updated.<br>";
            // Update row data
            $row = Capsule::table($tableName)->where('id', $rowId)->first();
        }

        // Create the form
        echo '<div style="margin:15px;">';
        echo '<div class="card mb-4">';
        echo '<div class="card-body">';
        echo '<h5 class="card-title">Edit Row</h5>';
        if (!empty($operationResult)) {
            echo '<div class="alert alert-info" role="alert">';
            echo $operationResult;
            echo '</div>';
        }
        echo '<form method="POST">';
        echo '<input type="hidden" name="update_row" value="1">';
        foreach ($columns as $column) {
            $value = htmlspecialchars($row->$column);
            if ($column === 'id') {
                echo '<input type="hidden" name="id" value="'. $value .'">';
                continue;
            }
            echo '
            <div class="form-group mb-3">
                <label for="'. $column .'">'. ucfirst($column) .'</label>
                <input type="text" class="form-control" id="'. $column .'" name="'. $column .'" value="'. $value .'" required>
            </div>
            ';
        }
        echo '
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="?module=whmcsdbmanager" class="btn btn-secondary">Cancel</a>
        </form>
        ';
        echo '</div>';
        echo '</div>'; // card closing
        echo '</div>'; // container closing
    } catch (\Exception $e) {
        echo '<div class="alert alert-danger" role="alert">';
        echo 'Error: ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
}
?>
