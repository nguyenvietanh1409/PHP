<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Utilities\VNPay;
use Illuminate\Http\Request;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\Mail;

class CheckOutController extends Controller
{
    //
    public function index(){
        $carts = Cart::content();
        $total = Cart::total();
        $subtotal = Cart::subtotal();

        return view('front.checkout.index', compact('carts','total','subtotal'));
    }

    public function addOrder(Request $request){
        //Thêm đơn hàng
        $order = Order::create($request->all());

        //THêm chi tiết đơn hàng
        $carts = Cart::content();
        foreach ($carts as $cart) {
            $data = [
                'order_id' => $order->id,
                'product_id' => $cart->id,
                'qty' => $cart->qty,
                'amount' => $cart->price,
                'total' => $cart->price * $cart->qty,
            ];
            OrderDetail::create($data);
        }

        if ($request->payment_type == 'pay_later'){
            //Gửi email
            $total = Cart::total();
            $subtotal = Cart::subtotal();
            $this->sendEmail($order,$total,$subtotal);

            //Xóa giỏ hàng
            Cart::destroy();

            //Trả về kết quả
            return 'Success! You will pay on delivery. Please check your email.';
        }
        if ($request->payment_type == 'online_payment'){
            // Lấy URL thanh toán VNPay
            $data_url = VNPay::vnpay_create_payment([
                'vnp_TxnRef' => $order->id,
                'vnp_OrderInfo' => 'Mô tảddownn hàng ở đây....',
                'vnp_Amount' => Cart::total(0, '', '') * 23000,
            ]);


            //Chuyển hướng tới URL lấy được
            return redirect()->to($data_url);

        }
    }

    public function vnPayCheck(Request  $request){
        //Lấy data từ URL
        $vnp_ResponseCode = $request->get('vnp_ResponseCode');
        $vnp_TxnRef = $request->get('vnp_TxnRef');
        $vnp_Amount = $request->get('vnp_Amount');

        //Kiểm tra kết quả giao dịch trả về từ VNPay
        if ($vnp_ResponseCode != null) {
            if ($vnp_ResponseCode == 00) {
                //Gửi email
                $order = Order::find($vnp_TxnRef);
                $total = Cart::total();
                $subtotal = Cart::subtotal();
                $this->sendEmail($order,$total,$subtotal);

                //Xóa giỏ hàng
                Cart::destroy($order);

                //Thông báo kết quả thành công
                return 'Success! Has paid online. Please check your email.';

            } else {//Nếu không thành công
                //Xóa đn hàng đã thêm vào Database, và trả về thông báo lỗi
                $order = Order::find($vnp_TxnRef)->delete();

                return 'ERROR: Payment';

            }
        }
    }

    private function sendEmail($order, $total, $subtotal){
        $email_to = $order->email;

        Mail::send('front.checkout.email', compact('order','total','subtotal'), function ($message) use ($email_to) {
            $message->from('vietanh20030914@gmail.com', 'Viet Anh');
            $message->to($email_to, $email_to);
            $message->subject('Order Notification');
        });
    }

}
