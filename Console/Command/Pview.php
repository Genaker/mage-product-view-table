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
use Mage\ProductView\Model\ProductView;
use Mage\ProductView\Setup\Recurring;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Pview extends Command
{

    private const NAME_ARGUMENT = "name";
    private const NAME_OPTION = "option";

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

        // Create View
        $startTime = microtime(true);
        $output->writeln("<success>Crete VIEW TABLE</success>");
        DB::statement($this->setup->dropViewSQL());
        DB::statement($this->setup->createViewTableFromSelect());
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $output->writeln("Create View Table time: <ok>" . $executionTime . ' </ok>');

        // Create MVIEW table
        $startTime = microtime(true);
        $output->writeln("<success>Create MVIEW table</success>");
        $this->setup->createTableFromView();
        $this->setup->populateTableFromView($this->setup->newTableName);
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $output->writeln("Create MVIEW time: <ok>" . $executionTime . ' </ok>');

        $this->setup->jsonProductTableCreate();

        $startTime = microtime(true);
        $output->writeln("<success>Start Product Json table populate</success>");
        $this->setup->populateProductJsonTableFromView();
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $output->writeln("END Product Json table populate time: <ok>" . $executionTime . ' </ok>');
        $output->writeln("<success>TEST:</success>");
        $startTime = microtime(true);
        $product = ProductView::selectRaw('SQL_NO_CACHE *')->first();
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $output->writeln("Load Product data from View using Model: <ok>" . $executionTime . ' </ok>');
        $startTime = microtime(true);
        $product = DB::table(DB::raw('catalog_product_view'))->select(DB::raw('SQL_NO_CACHE *'))->limit(1)->get();
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $output->writeln("Load Product data from View table: <ok>" . $executionTime . ' </ok>');
        $startTime = microtime(true);
        $product = DB::table(DB::raw('catalog_product_view_MVIEW'))->select(DB::raw('SQL_NO_CACHE *'))->limit(1)->get();
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        //dump($product);
        $output->writeln("Load Product data from MView table: <ok>" . $executionTime . ' </ok>');
        $startTime = microtime(true);
        $product = DB::table(DB::raw('product_json'))->select(DB::raw('SQL_NO_CACHE *'))->limit(1)->get();
        $productJsonData = json_decode($product[0]->data);
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        //dump($productJsonData);
        $output->writeln("Load Product data from JSON table: <ok>" . $executionTime . ' </ok>');
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
        $output->writeln("Load Product data using Magento Collection Code: <ok>" . $executionTime . ' </ok>');

        $startTime = microtime(true);
        $product = $this->productRepository->getById($prodcutId);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $output->writeln("Load Product data using Magento Product Repository Code: <ok>" . $executionTime . ' </ok>');
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
        $this->setDefinition([
            new InputArgument(self::NAME_ARGUMENT, InputArgument::OPTIONAL, "Name"),
            new InputOption(self::NAME_OPTION, "-a", InputOption::VALUE_NONE, "Option functionality"),
        ]);
        parent::configure();
    }
}
