<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare (strict_types = 1);

namespace Mage\ProductView\Console\Command;

use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Mage\DB2\DB2 as DB;
use Mage\DB2\Async;
use Mage\ProductView\Model\ProductJSON;
use Mage\ProductView\Model\ProductView;
use Mage\ProductView\Setup\Recurring;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Mage\DB2\formatTime;


class Pview extends Command
{

    private const NAME_ARGUMENT = "recreate";
    private const NAME_OPTION = "option";

    private $viewName = 'catalog_product_view';

    /**
     * @inheritdoc
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {

        $greenStyle = new OutputFormatterStyle('green', null, ['bold']);
        $output->getFormatter()->setStyle('success', $greenStyle);

        $blueStyle = new OutputFormatterStyle('blue', null, ['bold']);
        $output->getFormatter()->setStyle('ok', $blueStyle);

        $fullRun = $input->getArgument('full');
        DB::init();
        $startTime = microtime(true);
        sleep(2);
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $output->writeln("Formate time 2 sec: <ok>" . formatTime($executionTime) . ' </ok>');

        Async::instance()->preconect(10);
        $sufix = 0;
        $startSelect = microtime(true);
        $sql[] = DB::table(trim($this->viewName . '_' . $sufix, '_'))->selectRaw('SQL_NO_CACHE *')->orderBy('entity_id')->limit(10)->toSql();
        $sql[] = DB::table(trim($this->viewName . '_' . $sufix, '_'))->selectRaw('SQL_NO_CACHE *')->orderBy('entity_id')->skip(20)->take(40)->toSql();
        $sql[] = DB::table(trim($this->viewName . '_' . $sufix, '_'))->selectRaw('SQL_NO_CACHE *')->orderBy('entity_id')->skip(40)->take(60)->toSql();
        $sql[] = DB::table(trim($this->viewName . '_' . $sufix, '_'))->selectRaw('SQL_NO_CACHE *')->orderBy('entity_id')->skip(60)->take(80)->toSql();
        $sql[] = DB::table(trim($this->viewName . '_' . $sufix, '_'))->selectRaw('SQL_NO_CACHE *')->orderBy('entity_id')->skip(80)->take(100)->toSql();
        $result = Async::instance()->sendAsync($sql, debug: false, await: false);
        $endSelect = microtime(true);
        echo "Select Big SQL Async 5/100 Time: " . formatTime($endSelect - $startSelect) . "\n";
        $startSelect = microtime(true);
        $result = DB::table(trim($this->viewName . '_' . $sufix, '_'))->selectRaw('SQL_NO_CACHE *')->orderBy('entity_id')->limit(100)->get();
        $endSelect = microtime(true);
        echo "Select Big SQL Single Query Time: " . formatTime($endSelect - $startSelect) . "\n";
        $start = microtime(true);
        $startSelect = microtime(true);
        Async::instance()->asyncAwait();
        $endSelect = microtime(true);
        echo "Await Time: " . formatTime($endSelect - $startSelect) . "\n";

        // run with the *true* parameter to recreate or create all tables... 
        if ($fullRun === "true") {
            // Create View
            $startTime = microtime(true);
            $output->writeln("<success>Create VIEW TABLE 0</success>");
            DB::statement($this->setup->dropViewSQL(0));
            DB::statement($this->setup->createViewTableFromSelect(0));
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $output->writeln("Create View Table store 0 time: <ok>" . formatTime($executionTime) . ' </ok>');
            $output->writeln("<success>Create VIEW TABLE 1</success>");
            DB::statement($this->setup->dropViewSQL(1));
            DB::statement($this->setup->createViewTableFromSelect(1));
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $output->writeln("Create View Table store 1 time: <ok>" . formatTime($executionTime) . ' </ok>');

            // Create MVIEW table
            $startTime = microtime(true);
            $output->writeln("<success>Create and Populate MVIEW TABLE</success>");
            $startTime0 = microtime(true);
            $output->writeln("<success>Create MVIEW TABLE</success>");
            $this->setup->createTableFromView(0, true);
            $output->writeln("<success>Populate MVIEW TABLE</success>");
            $this->setup->populateTableFromView(0);
            $endTime0 = microtime(true);
            $executionTime0 = $endTime0 - $startTime0;
            $output->writeln("Populate MVIEW store 0 time: <ok>" . formatTime($executionTime0) . ' </ok>');

            $startTime1 = microtime(true);
            $this->setup->createTableFromView(1, true);
            $this->setup->populateTableFromView(1);
            $endTime1 = microtime(true);
            $executionTime1 = $endTime1 - $startTime1;
            $output->writeln("Populate MVIEW store 1 time: <ok>" . formatTime($executionTime1) . ' </ok>');

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $output->writeln("Create MVIEW total time: <ok>" . formatTime($executionTime) . ' </ok>');

            $output->writeln("<success>Start Product Json table populate</success>");
            $this->setup->jsonProductTableCreate(true);
            $startTime = microtime(true);
            $this->setup->populateProductJsonTableFromView([0]);
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $output->writeln("END Product JSON table store 0 populate time: <ok>" . formatTime($executionTime) . ' </ok>');

            $startTime = microtime(true);
            $this->setup->populateProductJsonTableFromView([1]);
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $output->writeln("END Product JSON table store 1 populate time: <ok>" . formatTime($executionTime) . ' </ok>');
        }

        $output->writeln("<success>PERFORMANCE TESTs:</success>");
        $startTime = microtime(true);
        DB::init();
        $product = (new ProductView())->fromStore(1)->selectRaw('SQL_NO_CACHE *')->first();
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $output->writeln("Load Product data from View using Eloquent Model: <ok>" . formatTime($executionTime) . ' </ok>');

        $startTime = microtime(true);
        $product = (new ProductJSON())->selectRaw('SQL_NO_CACHE *')->where('store_id', '=', 1)->limit(1)->get();
        //dump($product->attributes);
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $output->writeln("Load Product data from JSON using Eloquent Model: <ok>" . formatTime($executionTime) . ' </ok>');

        $startTime = microtime(true);
        $product = DB::table(DB::raw('catalog_product_view_0'))->select(DB::raw('SQL_NO_CACHE *'))->limit(1)->get();
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $output->writeln("Load Product data from View table: <ok>" . formatTime($executionTime) . ' </ok>');

        $startTime = microtime(true);
        $product = DB::table(DB::raw('catalog_product_view_MVIEW_1'))->select(DB::raw('SQL_NO_CACHE *'))->limit(1)->get();
        //dump($product);
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        //dump($product);
        $output->writeln("Load Product data from MView(Materialized View) table: <ok>" . formatTime($executionTime) . ' </ok>');

        $startTime = microtime(true);
        $product = DB::table(DB::raw('product_json'))->select(DB::raw('SQL_NO_CACHE *'))->where('store_id', '=', 1)->limit(1)->get();
        $product[0]->attributes = json_decode($product[0]->attributes);
        //dump($product);
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        //dump($productJsonData);
        $output->writeln("Load Product data from JSON table: <ok>" . formatTime($executionTime) . ' </ok>');

        $startTime = microtime(true);
        $collection = $this->productCollectionFactory->create();

        $product = $collection->addAttributeToSelect('*') // Select all attributes
            ->addAttributeToFilter('status', 1) // Ensure the product is enabled
            ->addAttributeToFilter('visibility', ['neq' => 1]) // Exclude not visible individually
            ->setPageSize(1) // Limit to 1 product
            ->setCurPage(1)->getFirstItem();

        $endTime = microtime(true);
        $prodcutId = $product->getId();
        $executionTime = $endTime - $startTime;
        $output->writeln("Load Product data using Magento Product Collection Code: <ok>" . formatTime($executionTime) . ' </ok>');

        $startTime = microtime(true);
        $product = $this->productRepository->getById($prodcutId);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $output->writeln("Load Product data using Magento Product Repository Code: <ok>" . formatTime($executionTime) . ' </ok>');
        return 0;
    }

    public function __construct(
        private Recurring $setup,
        private CollectionFactory $productCollectionFactory,
        private ProductRepository $productRepository,
        string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName("pview:run");
        $this->setDescription("run view create, MVIEW and json table populate");
        $this->addArgument(
            'full',
            InputArgument::OPTIONAL,
            'Full program run',
            'false' // Default value
        );
        parent::configure();
    }
}
