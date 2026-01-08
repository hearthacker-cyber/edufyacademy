<?php
require_once __DIR__ . '/../vendor1/autoload.php';

use Razorpay\Api\Api;

class RazorpayConfig {
    private $api;
    
    public function __construct() {
        // Use your live keys
        $key_id = 'rzp_live_RIzKy4r0xdlrkb';
        $key_secret = '09wg47fN3IVFocx5PbjiDAWr';
        
        $this->api = new Api($key_id, $key_secret);
    }
    
    public function getApi() {
        return $this->api;
    }
    
    public function getKeyId() {
        return 'rzp_live_RIzKy4r0xdlrkb';
    }
    
    public function getKeySecret() {
        return '09wg47fN3IVFocx5PbjiDAWr';
    }
}
?>