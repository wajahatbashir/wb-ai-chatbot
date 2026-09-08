<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Order;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Pdf\InvoiceFactory as InvoicePdfFactory;
use WB\AiChatbot\Model\Chat\OrderLookup;

/**
 * Streams Magento's own invoice PDF for a signed, expiring link issued by the chat (guest-safe: no login needed,
 * the signature proves the chat verified the order's email).
 */
class Invoice implements ActionInterface, HttpGetActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var OrderLookup
     */
    private $orderLookup;

    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @var InvoicePdfFactory
     */
    private $pdfFactory;

    /**
     * @var FileFactory
     */
    private $fileFactory;

    /**
     * @var RawFactory
     */
    private $rawFactory;

    public function __construct(
        RequestInterface $request,
        OrderLookup $orderLookup,
        InvoiceRepositoryInterface $invoiceRepository,
        InvoicePdfFactory $pdfFactory,
        FileFactory $fileFactory,
        RawFactory $rawFactory
    ) {
        $this->request = $request;
        $this->orderLookup = $orderLookup;
        $this->invoiceRepository = $invoiceRepository;
        $this->pdfFactory = $pdfFactory;
        $this->fileFactory = $fileFactory;
        $this->rawFactory = $rawFactory;
    }

    public function execute()
    {
        $orderId = (int)$this->request->getParam('order');
        $invoiceId = (int)$this->request->getParam('invoice');
        $expires = (int)$this->request->getParam('exp');
        $signature = (string)$this->request->getParam('sig');
        if (!$orderId || !$invoiceId || !$this->orderLookup->isValidInvoiceLink($orderId, $invoiceId, $expires, $signature)) {
            return $this->rawFactory->create()->setHttpResponseCode(403)->setContents('This invoice link is invalid or has expired. Ask the chat assistant for a new one.');
        }
        try {
            $invoice = $this->invoiceRepository->get($invoiceId);
        } catch (\Throwable $e) {
            return $this->rawFactory->create()->setHttpResponseCode(404)->setContents('Invoice not found.');
        }
        if ((int)$invoice->getOrderId() !== $orderId) {
            return $this->rawFactory->create()->setHttpResponseCode(403)->setContents('Invoice does not belong to this order.');
        }
        $pdf = $this->pdfFactory->create()->getPdf([$invoice]);
        return $this->fileFactory->create(
            'invoice-' . $invoice->getIncrementId() . '.pdf',
            $pdf->render(),
            DirectoryList::VAR_DIR,
            'application/pdf'
        );
    }
}
