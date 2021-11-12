<?php
if (!defined('_TB_VERSION_')) {
    if (php_sapi_name() !== 'cli') {
        exit;
    } else {
        $first = true;
        foreach ($argv as $arg) {
            if ($first) {
                $first = false;
                continue;
            }

            $arg = substr($arg, 2); // --
            $e = explode('=', $arg);
            if (count($e) == 2) {
                $_GET[$e[0]] = $e[1];
            } else {
                $_GET[$e[0]] = true;
            }
        }
        $_GET['module'] = 'cronjobs';
        $_GET['fc'] = 'module';
        $_GET['controller'] = 'cron';

        require_once __DIR__ . '/../../../../config/config.inc.php';
        require_once __DIR__ . '/../../elasticsearch.php';
    }
}

/**
 * Class ElasticsearchcronModuleFrontController
 */
class ElasticsearchcronModuleFrontController extends ModuleFrontController
{
    /**
     * Run the cron job
     *
     * ElasticsearchcronModuleFrontController constructor.
     *
     * @throws PrestaShopException
     * @throws Adapter_Exception
     * @throws SmartyException
     */
    public function __construct()
    {
        // Use admin user for indexing
        Context::getContext()->employee = new Employee(Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            (new DbQuery())
                ->select('`' . bqSQL(Employee::$definition['primary']) . '`')
                ->from(bqSQL(Employee::$definition['table']))
                ->where('`id_profile` = 1')
        ));

        if (isset($_GET['id_shop'])) {
            $idShop = (int)$_GET['id_shop'];
        } else {
            $idShop = Context::getContext()->shop->id;
        }

        if (isset($_GET['clear'])) {
            try {
                // Delete the indices first
                ElasticsearchModule\Indexer::eraseIndices(null, [$idShop]);

                // Reset the mappings
                ElasticsearchModule\Indexer::createMappings(null, [$idShop]);

                // Erase the index status for the current store
                ElasticsearchModule\IndexStatus::erase($idShop);
            } catch (Exception $e) {
            }
        }

        $chunks = INF;
        if (isset($_GET['chunks'])) {
            $chunks = (int)$_GET['chunks'];
        }

        /** @var Elasticsearch $module */
        $module = Module::getInstanceByName('elasticsearch');
        $module->cronProcessRemainingProducts($chunks, $idShop);

        parent::__construct();
    }
}

if (php_sapi_name() === 'cli') {
    try {
        new ElasticsearchcronModuleFrontController();
    } catch (Exception $e) {
        die("Error: $e");
    }
}
