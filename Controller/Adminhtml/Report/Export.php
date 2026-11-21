<?php
declare(strict_types=1);

namespace StorvixLabs\CartInsight\Controller\Adminhtml\Report;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use StorvixLabs\CartInsight\ViewModel\Report;

/**
 * Downloads the abandoned carts of the selected period (without the 50 row limit of the page) as CSV.
 */
class Export extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'StorvixLabs_CartInsight::report';

    public function __construct(
        Context $context,
        private readonly FileFactory $fileFactory,
        private readonly Report $report
    ) {
        parent::__construct($context);
    }

    public function execute(): ResponseInterface
    {
        $criteria = $this->report->getCriteria();
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Quote ID', 'Last Activity (UTC)', 'Email', 'Customer ID', 'Items', 'Qty', 'Value (base)', 'Reached', 'Payment Method'], ',', '"', '');
        foreach ($this->report->getCarts(5000) as $cart) {
            fputcsv($stream, [
                $cart['quote_id'],
                $cart['updated_at'],
                $this->safe((string)$cart['customer_email']),
                $cart['customer_id'],
                $cart['items_count'],
                (float)$cart['items_qty'],
                $cart['value'],
                $cart['stage_label'],
                $cart['payment_title'],
            ], ',', '"', '');
        }
        rewind($stream);
        $content = (string)stream_get_contents($stream);
        fclose($stream);

        return $this->fileFactory->create(
            sprintf('abandoned-carts-%s-%s.csv', $criteria->from, $criteria->to),
            $content,
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }

    /**
     * Stops spreadsheet programs from running a cell as a formula.
     */
    private function safe(string $value): string
    {
        return $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false ? "'" . $value : $value;
    }
}
