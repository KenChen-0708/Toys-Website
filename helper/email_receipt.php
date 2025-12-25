<?php

require_once __DIR__ . '/mailer.php';

/**
 * Send order receipt email to customer
 *
 * @param array $order Order details
 * @param array $order_items Array of order items
 * @param array $member Member details
 * @param array $checkout_data Checkout data including shipping address
 * @return bool true on success, false on failure
 */
function send_order_receipt_email($order, $order_items, $member, $checkout_data) {
    try {
        // Validate required data
        if (empty($order) || empty($order_items) || empty($member) || empty($checkout_data)) {
            error_log("Email receipt error: Missing required data");
            return false;
        }
        
        if (empty($member['email']) || !filter_var($member['email'], FILTER_VALIDATE_EMAIL)) {
            error_log("Email receipt error: Invalid email address - " . ($member['email'] ?? 'empty'));
            return false;
        }
        
        $member_name = htmlspecialchars($member['username'] ?? 'Customer');
        $order_id = $order['order_id'];
        $order_date = date('F j, Y g:i A', strtotime($order['order_date']));
        $total_amount = number_format($order['total_amount'], 2);
        
        // Build shipping address safely
        $shipping_address = htmlspecialchars($checkout_data['shipping_address']['address_line1'] ?? '');
        if (!empty($checkout_data['shipping_address']['address_line2'])) {
            $shipping_address .= '<br>' . htmlspecialchars($checkout_data['shipping_address']['address_line2']);
        }
        $shipping_address .= '<br>' . htmlspecialchars($checkout_data['shipping_address']['city'] ?? '') . ', ' . 
                            htmlspecialchars($checkout_data['shipping_address']['state'] ?? '') . ' ' . 
                            htmlspecialchars($checkout_data['shipping_address']['postal_code'] ?? '');
        $shipping_address .= '<br>' . htmlspecialchars($checkout_data['shipping_address']['country'] ?? '');
        
        // Build order items HTML
        $items_html = '';
        $subtotal = 0;
        foreach ($order_items as $item) {
            $item_total = $item['quantity'] * $item['price'];
            $subtotal += $item_total;
            
            $items_html .= '
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    ' . htmlspecialchars($item['product_name'] ?? 'Unknown Product') . '
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: center;">
                    ' . intval($item['quantity']) . '
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right;">
                    RM' . number_format($item['price'], 2) . '
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right; font-weight: 600;">
                    RM' . number_format($item_total, 2) . '
                </td>
            </tr>';
        }
        
        // Calculate shipping fee
        $shipping_fee = $subtotal >= 15.00 ? 0 : 5.00;
        $discount = $subtotal + $shipping_fee - $order['total_amount'];
        
        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Order Receipt</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
            <div style="max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1);">
                
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 30px; text-align: center;">
                    <h1 style="margin: 0; font-size: 28px; font-weight: 700;">Order Confirmation</h1>
                    <p style="margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;">Thank you for your purchase!</p>
                </div>
                
                <!-- Order Info -->
                <div style="padding: 30px;">
                    <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                        <h2 style="margin: 0 0 15px 0; color: #333; font-size: 20px;">Order Details</h2>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #666;">Order Number:</span>
                            <span style="font-weight: 600; color: #4CAF50;">#' . str_pad($order_id, 6, '0', STR_PAD_LEFT) . '</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #666;">Order Date:</span>
                            <span style="font-weight: 600;">' . $order_date . '</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #666;">Status:</span>
                            <span style="font-weight: 600; color: #FF9800;">Pending</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #666;">Total Amount:</span>
                            <span style="font-weight: 700; font-size: 18px; color: #4CAF50;">RM' . $total_amount . '</span>
                        </div>
                    </div>
                    
                    <!-- Items Table -->
                    <div style="margin-bottom: 25px;">
                        <h3 style="margin: 0 0 15px 0; color: #333; font-size: 18px;">Order Items</h3>
                        <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="padding: 15px 12px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #eee;">Product</th>
                                    <th style="padding: 15px 12px; text-align: center; font-weight: 600; color: #333; border-bottom: 2px solid #eee;">Qty</th>
                                    <th style="padding: 15px 12px; text-align: right; font-weight: 600; color: #333; border-bottom: 2px solid #eee;">Price</th>
                                    <th style="padding: 15px 12px; text-align: right; font-weight: 600; color: #333; border-bottom: 2px solid #eee;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                ' . $items_html . '
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Price Breakdown -->
                    <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                        <h3 style="margin: 0 0 15px 0; color: #333; font-size: 18px;">Price Summary</h3>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; padding: 5px 0;">
                            <span style="color: #666;">Subtotal:</span>
                            <span>RM' . number_format($subtotal, 2) . '</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; padding: 5px 0;">
                            <span style="color: #666;">Shipping:</span>
                            <span>' . ($shipping_fee > 0 ? 'RM' . number_format($shipping_fee, 2) : '<span style="color: #4CAF50; font-weight: 600;">FREE</span>') . '</span>
                        </div>';
        
        if ($discount > 0) {
            $htmlBody .= '
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; padding: 5px 0;">
                            <span style="color: #666;">Discount:</span>
                            <span style="color: #4CAF50; font-weight: 600;">-RM' . number_format($discount, 2) . '</span>
                        </div>';
        }
        
        $htmlBody .= '
                        <div style="border-top: 2px solid #ddd; margin-top: 10px; padding-top: 10px;">
                            <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: 700;">
                                <span style="color: #333;">Total:</span>
                                <span style="color: #4CAF50;">RM' . $total_amount . '</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Shipping Address -->
                    <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                        <h3 style="margin: 0 0 15px 0; color: #333; font-size: 18px;">Shipping Address</h3>
                        <div style="color: #555; line-height: 1.6;">
                            ' . $shipping_address . '
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style="text-align: center; padding: 20px 0; border-top: 1px solid #eee;">
                        <p style="margin: 0 0 10px 0; color: #666;">Thank you for shopping with Toy World!</p>
                        <p style="margin: 0; color: #999; font-size: 14px;">
                            If you have any questions about your order, please contact us at 
                            <a href="mailto:kaijian1003@gmail.com" style="color: #4CAF50; text-decoration: none;">kaijian1003@gmail.com</a>
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        
        // Plain text version
        $altBody = "Order Confirmation - Toy World\n\n";
        $altBody .= "Hi {$member_name},\n\n";
        $altBody .= "Thank you for your purchase! Here are your order details:\n\n";
        $altBody .= "Order Number: #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . "\n";
        $altBody .= "Order Date: {$order_date}\n";
        $altBody .= "Status: Pending\n";
        $altBody .= "Total Amount: RM{$total_amount}\n\n";
        
        $altBody .= "Order Items:\n";
        $altBody .= "----------------------------------------\n";
        foreach ($order_items as $item) {
            $item_total = $item['quantity'] * $item['price'];
            $altBody .= ($item['product_name'] ?? 'Unknown Product') . " x" . $item['quantity'] . " - RM" . number_format($item_total, 2) . "\n";
        }
        $altBody .= "----------------------------------------\n";
        $altBody .= "Subtotal: RM" . number_format($subtotal, 2) . "\n";
        $altBody .= "Shipping: " . ($shipping_fee > 0 ? "RM" . number_format($shipping_fee, 2) : "FREE") . "\n";
        if ($discount > 0) {
            $altBody .= "Discount: -RM" . number_format($discount, 2) . "\n";
        }
        $altBody .= "Total: RM{$total_amount}\n\n";
        
        $altBody .= "Shipping Address:\n";
        $altBody .= strip_tags(str_replace('<br>', "\n", $shipping_address)) . "\n\n";
        $altBody .= "Thank you for shopping with Toy World!\n";
        $altBody .= "If you have any questions, contact us at kaijian1003@gmail.com";
        
        // Send the email
        return send_app_mail(
            $member['email'],
            $member_name,
            'Order Confirmation #' . str_pad($order_id, 6, '0', STR_PAD_LEFT) . ' - Toy World',
            $htmlBody,
            $altBody
        );
        
    } catch (Exception $e) {
        error_log("Email receipt error: " . $e->getMessage());
        return false;
    }
}