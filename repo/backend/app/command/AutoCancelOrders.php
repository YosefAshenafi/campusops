<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\service\OrderService;

class AutoCancelOrders extends Command
{
    protected function configure()
    {
        $this->setName('orders:auto-cancel')
            ->setDescription('Cancel orders pending payment for more than 30 minutes');
    }

    protected function execute(Input $input, Output $output)
    {
        $orderService = new OrderService();

        $orders = \app\model\Order::where('state', 'pending_payment')
            ->where('auto_cancel_at', '<=', date('Y-m-d H:i:s'))
            ->select();

        $count = 0;
        foreach ($orders as $order) {
            try {
                $orderService->cancelBySystem($order->id, 'Auto-cancelled: payment not received within 30 minutes');
                $count++;
            } catch (\Exception $e) {
                $output->writeln("Failed to cancel order {$order->id}: " . $e->getMessage());
            }
        }

        $output->writeln("Cancelled {$count} orders.");
    }
}