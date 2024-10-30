<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://i19s.com
 * @since      1.0.0
 *
 * @package    Ingenious_Woocommerce
 * @subpackage Ingenious_Woocommerce/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Ingenious_Woocommerce
 * @subpackage Ingenious_Woocommerce/public
 * @author     Ingenious Development Team <wordpress@i19s.com>
 */
class Ingenious_Woocommerce_Public
{

    const INGENIOUS_ORDER_UUID_NAMESPACE = 'fb632d4f-48aa-4067-938e-eb3d36d178b0';

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    private $cookieName;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version The version of this plugin.
     * @since    1.0.0
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->cookieName = '_iclid';

        $this->load_dependencies();
    }

    private function load_dependencies()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-uuid.php';
    }

    public function i19s_wp_send_headers()
    {
        $clickIds = $this->getClickIdsFromCookie();
        if (isset($_GET['clickid'])) {
            $clickIds[] = sanitize_text_field($_GET['clickid']);
        }
        if (isset($_GET['clickId'])) {
            $clickIds[] = sanitize_text_field($_GET['clickId']);
        }
        if (isset($_GET['iclid'])) {
            $clickIds[] = sanitize_text_field($_GET['iclid']);
        }

        $clickIds = array_unique($clickIds);

        if (count($clickIds) > 0) {
            $clickIdsString = implode(",", $clickIds);
            setcookie($this->cookieName, $clickIdsString, time() + 31536000, '/');
        }
    }

    /**
     * Generate code for each public page
     *
     * @since    1.0.0
     */
    public function i19s_wp_head()
    {

        $trackingDomain = sanitize_text_field(get_option('ingenious_woocommerce_tracking_domain', ''));
        $advertiserId = sanitize_text_field(get_option('ingenious_woocommerce_advertiser_id', ''));
        $requestUri = esc_url(home_url($_SERVER['REQUEST_URI']));
        $referer = wp_get_referer();

        $customerIdHashed = '';
        if (function_exists('wp_get_current_user') && isset(wp_get_current_user()->ID)) {
            $customerIdHashed = sha1(wp_get_current_user()->ID);
        }

        $gdpr = '';
        $gdprConsent = '';
        $pluginVersion = '4.25.woocommerce-' . INGENIOUS_WOOCOMMERCE_VERSION;

//        if (is_admin() || current_user_can('manage_options')) {
//            return false;
//        }

        if (is_checkout() && !is_order_received_page()) {
            // we are on the checkout process
            return false;
        }

        if (is_checkout() && is_order_received_page()) {
            // we are on the finished checkout process (a.k.a 'thank you' page)
            self::insert_conversion_tag($pluginVersion, $trackingDomain, $advertiserId, $requestUri, $referer, $customerIdHashed, $gdpr, $gdprConsent);
            return true;
        }

        // we are on an ordinary public page
        self::insert_onpage_tag($pluginVersion, $trackingDomain, $advertiserId, $requestUri, $referer, $customerIdHashed, $gdpr, $gdprConsent);
    }

    private function insert_conversion_tag($pluginVersion, $trackingDomain, $advertiserId, $requestUri, $referer, $customerId, $gdpr, $gdprConsent)
    {
        global $wp;
        $orderId = $wp->query_vars['order-received'] ?? 0;

        if (!$orderId) {
            return;
        }

        $clickIds = $this->getClickIdsFromCookie();
        $clickIdsFormatted = implode(",", $clickIds);

        $order = new WC_Order($orderId);
        $orderId = $order->get_id();
        $conversionUniqueId = UUID::uuid3(self::INGENIOUS_ORDER_UUID_NAMESPACE, $orderId);
        $orderCustomerId = $order->get_customer_id();
        $orderUser = $order->get_user();

        $date1 = new DateTime($orderUser->user_registered);
        $date2 = new DateTime($order->get_date_created()->date('Y-m-d H:i:s'));
        $orderDateUserRegistrationDateDiff = $date2->getTimestamp() - $date1->getTimestamp();

        $paymentMethod = $order->get_payment_method_title();

        $isNewCustomer = false;
        if ($orderCustomerId == 0 || $orderUser == null || $orderDateUserRegistrationDateDiff < 60) {
            $isNewCustomer = true;
        }

        $orderTotal = $order->get_total();
        $orderTaxTotal = $order->get_total_tax();
        $orderShippingTotal = $order->get_shipping_total();

        $orderDiscountCodes = [];
        // sum of all discounts amounts of all coupons
        $orderDiscountValue = 0;
        $order_coupons = $order->get_items('coupon');
        foreach ($order_coupons as $coupon) {
            $orderDiscountCodes[] = $coupon->get_code();
            $discountAmount = $coupon->get_discount();
            $orderDiscountValue += $discountAmount;
        }

        // net value without taxes, shipping cost and discounts
        $orderValue = $orderTotal + $orderDiscountValue - $orderTaxTotal - $orderShippingTotal;

        // net value without taxes and shipping cost, and without total coupon amount
        $orderDiscountedOrderValue = $orderValue - $orderDiscountValue;

        // total value including taxes and shipping cost, but without coupon amount
        $invoiceValue = $orderTotal;

        if (count($orderDiscountCodes) > 1) {
            $order_discount_codes_string = "[\"" . implode("\",\"", $orderDiscountCodes) . "\"]";;
        } else {
            $order_discount_codes_string = $orderDiscountCodes[0];
        }

        $orderCurrency = $order->get_currency();

        $conversionTarget = 'sale';
        $trackingCategory = 'basket';
        $basketTrackingCategory = 'default';

        // Iterating through each WC_Order_Item_Product objects
        $basket = [];
        foreach ($order->get_items() as $item_key => $item):
            $product = $item->get_product();

            $itemSubTotal = $item->get_subtotal();
            $itemQuantity = $item->get_quantity();
            $itemPrice = $itemSubTotal / $itemQuantity;

            // format monetrary value
            $item_price_formatted = number_format($itemPrice, wc_get_price_decimals(), '.', '');

            $basket[] = [
                'pid' => $product->get_sku(),
                'prn' => $product->get_name(),
                'brn' => self::get_product_brand_name($product),
                'pri' => $item_price_formatted,
                'qty' => $itemQuantity,
                'trc' => $basketTrackingCategory,
                'prc' => self::get_product_category_names($product)
            ];
        endforeach;

        $basketJson = wp_json_encode($basket, JSON_UNESCAPED_SLASHES);

        $additionalData = '';
        $userValue1 = '';
        $userValue2 = '';
        $userValue3 = '';
        $userValue4 = '';
        $confStat = '';
        $customerGender = '';
        $customerAge = '';
        $customerSurvey = '';
        $sessionId = '';
        $admCode = '';
        $subCode = '';

        $itsConv = wp_json_encode([
            'trcDomain' => $trackingDomain,
            'advId' => $advertiserId,

            'uniqid' => $conversionUniqueId,
            'convId' => $orderId,
            'convTarget' => $conversionTarget,
            'trcCat' => $trackingCategory,

            'ordCurr' => $orderCurrency,
            'invValue' => self::formnat_english_number_format($invoiceValue),
            'ordValue' => self::formnat_english_number_format($orderValue),
            'basket' => addslashes($basketJson),

            'discCode' => $order_discount_codes_string,
            'discValue' => self::formnat_english_number_format($orderDiscountValue),
            'discOrdValue' => self::formnat_english_number_format($orderDiscountedOrderValue),

            'isCustNew' => ($isNewCustomer ? 'true' : 'false'),
            'custId' => $customerId,
            'custGender' => $customerGender,
            'custAge' => $customerAge,
            'custSurv' => $customerSurvey,
            'payMethod' => $paymentMethod,

            'userVal1' => $userValue1,
            'userVal2' => $userValue2,
            'userVal3' => $userValue3,
            'userVal4' => $userValue4,
            'addData' => $additionalData,

            'locationHref' => $requestUri,
            'referrer' => $referer,
            'siteId' => $requestUri,

            'gdpr' => $gdpr,
            'gdprConsent' => $gdprConsent,

            'clickIds' => $clickIds,
            'sessionId' => $sessionId,
            'admCode' => $admCode,
            'subCode' => $subCode,
            'confStat' => $confStat,

        ], JSON_UNESCAPED_SLASHES);

        $imageUrl = 'https://' . $trackingDomain . '/ts/' . $advertiserId . '/tsa?typ=i'
            . '&trc=' . urlencode($trackingCategory)
            . '&ctg=' . urlencode($conversionTarget)
            . '&cid=' . urlencode($orderId)
            . '&orv=' . urlencode(self::formnat_english_number_format($orderValue))
            . '&orc=' . urlencode($orderCurrency)
            . '&dsv=' . urlencode(self::formnat_english_number_format($orderDiscountValue))
            . '&ovd=' . urlencode(self::formnat_english_number_format($orderDiscountedOrderValue))
            . '&dsc=' . urlencode($order_discount_codes_string)
            . '&inv=' . urlencode(self::formnat_english_number_format($invoiceValue))
            . '&cfs=' . urlencode($confStat)
            . '&amc=' . urlencode($admCode)
            . '&pmt=' . urlencode($paymentMethod)
            . '&smc=' . urlencode($subCode)
            . '&uv1=' . urlencode($userValue1)
            . '&uv2=' . urlencode($userValue2)
            . '&uv3=' . urlencode($userValue3)
            . '&uv4=' . urlencode($userValue4)
            . '&csn=' . urlencode(($isNewCustomer ? 'true' : 'false'))
            . '&csi=' . urlencode($customerId)
            . '&csg=' . urlencode($customerGender)
            . '&csa=' . urlencode($customerAge)
            . '&bsk=' . urlencode($basketJson)
            . '&adt=' . urlencode($additionalData)
            . '&uniqid=' . urlencode($conversionUniqueId)
            . '&cli=' . urlencode($clickIdsFormatted)
            . '&csr=' . urlencode($customerSurvey)
            . '&sid=' . urlencode($requestUri)
            . '&hrf=' . urlencode($requestUri)
            . '&gdpr=' . urlencode($gdpr)
            . '&gdpr_consent=' . urlencode($gdprConsent)
            . '&ver=' . urlencode($pluginVersion);
        ?>

      <script type="text/javascript">
        var itsConv = JSON.parse('<?php
            // sanity-check has been done usíng wp_json_encode
            print $itsConv;
            ?>');

        // @formatter:off
        en=function(v){if(v){if(typeof(encodeURIComponent)=='function'){return(encodeURIComponent(v));}return(escape(v));}};ts=function(){var d=new Date();var t=d.getTime();return(t);};im=function(s){if(document.images){if(typeof(ia)!="object"){
          var ia=new Array();};var i=ia.length;ia[i]=new Image();ia[i].src=s;ia[i].onload=function(){};}else{document.write('<img src="'+s+'" height="1" width="1" border="0" alt="" style="display:none;">');}};var pr='https:';
        fr=function(s){var d=document;var i=d.createElement("iframe");i.src=s;i.frameBorder=0;i.width=0;i.height=0;i.vspace=0;i.hspace=0;i.marginWidth=0;i.marginHeight=0;i.scrolling="no";i.allowTransparency=true;i.style.display="none";try{d.body.insertBefore(i,d.body.firstChild);}catch(e){
          d.write('<ifr'+'ame'+' src="'+s+'" width="0" height="0" frameborder="0" vspace="0" hspace="0" marginwidth="0" marginheight="0" scrolling="no" allowtransparency="true" style="display:none;"></ifr'+'ame>');}};ap=function(o){var v='tst='+ts();if(o.trcCat){v+='&trc='+en(o.trcCat);}
          v+='&ctg='+en(o.convTarget);v+='&cid='+en(o.convId);if(o.ordValue){v+='&orv='+en(o.ordValue);}if(o.ordCurr){v+='&orc='+en(o.ordCurr);}if(o.discValue){v+='&dsv='+en(o.discValue);}if(o.discOrdValue){v+='&ovd='+en(o.discOrdValue);}if(o.discCode){v+='&dsc='+en(o.discCode);}
          if(o.invValue){v+='&inv='+en(o.invValue);}if(o.confStat){v+='&cfs='+en(o.confStat);}if(o.admCode){v+='&amc='+en(o.admCode);}if(o.payMethod){v+='&pmt='+en(o.payMethod);}if(o.subCode){v+='&smc='+en(o.subCode);}if(o.userVal1){v+='&uv1='+en(o.userVal1);}if(o.userVal2){v+='&uv2='+en(o.userVal2);}if(o.userVal3){
            v+='&uv3='+en(o.userVal3);}if(o.userVal4){v+='&uv4='+en(o.userVal4);}if(o.isCustNew){var n=o.isCustNew.toLowerCase();v+='&csn=';v+=(n=="true"||n=="false")?n:"null";}if(o.custId){v+='&csi='+en(o.custId);}if(o.custGend){var g=o.custGend.toLowerCase();v+='&csg=';
            v+=(g=="m"||g=="f")?g:"null";}if(o.custAge){v+='&csa='+en(o.custAge);}if(o.basket){v+='&bsk='+en(o.basket);}if(o.addData){v+='&adt='+en(o.addData);}if(o.uniqid){v+='&uniqid='+en(o.uniqid);}if(o.clickIds && o.clickIds.length > 0){v+='&cli='+en(o.clickIds.join(','));}else if(o.clickId){v+='&cli='+en(o.clickId);}if(o.custSurv){v+='&csr='+en(o.custSurv);}if(o.siteId){v+='&sid='+en(o.siteId);}var s=(screen.width)?screen.width:"0";
          s+="X";s+=(screen.height)?screen.height:"0";s+="X";s+=(screen.colorDepth)?screen.colorDepth:"0";v+='&scr='+s;v+='&nck=';v+=(navigator.cookieEnabled)?navigator.cookieEnabled:"null";v+='&njv=';v+=(navigator.javaEnabled())?navigator.javaEnabled():"null";if (o.locationHref){v+='&hrf='+en(o.locationHref);}if(o.gdpr){v+='&gdpr='+en(o.gdpr);}if(o.gdprConsent){v+='&gdpr_consent='+en(o.gdprConsent);}v+='&ver='+en('<?php print esc_js($pluginVersion); ?>');return(v);};
        itsStartConv=function(o){var s=pr+'//'+o.trcDomain+'/ts/'+o.advId+'/tsa?typ=f&'+ap(o);fr(s);};itsStartConv(itsConv);

        var a = document.createElement('script'); a.type = 'text/javascript'; a.async = true; a.src = 'https://'+itsConv.trcDomain+'/scripts/ts/'+itsConv.advId+'contA.js'; var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(a, s);
        // @formatter:on
      </script>

      <!-- BEGIN Ingenious Partner Marketing Platform – conversion tag version '<?php print esc_js($pluginVersion); ?>' -->
      <noscript>
        <img src="<?php print esc_url($imageUrl); ?>" width="1" height="1" style="display:none;"
             alt="">
      </noscript>
      <!-- END Ingenious Partner Marketing Platform – conversion tag version '<?php print esc_js($pluginVersion); ?>' -->
        <?php
    }

    private function formnat_english_number_format($num)
    {
        return number_format($num, wc_get_price_decimals(), '.', '');
    }

    private function get_product_category_names($product)
    {
//        $categories = get_the_terms($product->ID, 'product_cat');
        return '';
    }

    private function get_product_brand_name($product)
    {
//        $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
//        foreach ( $active_plugins as $plugin ) {
//            if ( strpos( $plugin, 'wp-seo' ) ) {
//                return true;
//            }
//        }
//
//        $pa_brand = get_the_terms($product->get_id(), 'pa_brand');
//        $product_brand = get_the_terms($product->get_id(), 'product_brand');
//        $yith_product_brands = get_the_terms($product->get_id(), 'yith_product_brand');
//        $pa_brand = get_the_terms($product->get_id(), 'pa_brand');

        return '';
    }

    private function insert_onpage_tag($pluginVersion, $trackingDomain, $advertiserId, $requestUri, $referer, $customerId, $gdpr, $gdprConsent)
    {
        $productSku = '';
        if (is_product()) {
            // we are on a single product page
            $productSku = wc_get_product()->get_sku();
        }

        $userValue1 = '';
        $userValue2 = '';

        $itsClickPI = wp_json_encode([
            'siteId' => $requestUri,
            'productId' => $productSku,
            'userVal1' => $userValue1,
            'userVal2' => $userValue2,
            'locationHref' => $requestUri,
            'referrer' => $referer,
            'custId' => $customerId,
            'gdpr' => $gdpr,
            'gdprConsent' => $gdprConsent,
            'advId' => $advertiserId,
            'trcDomain' => $trackingDomain
        ], JSON_UNESCAPED_SLASHES);

        $imageUrl = 'https://' . $trackingDomain . '/ts/' . $advertiserId . '/tsc?'
            . 'sid=' . urlencode($requestUri)
            . '&pid=' . urlencode($productSku)
            . '&uv1=' . urlencode($userValue1)
            . '&uv2=' . urlencode($userValue2)
            . '&rrf=' . urlencode($referer)
            . '&gdpr=' . urlencode($gdpr)
            . '&gdpr_consent=' . urlencode($gdprConsent)
            . '&hrf=' . urlencode($requestUri)
            . '&csi=' . urlencode($customerId)
            . '&rmd=' . urlencode('0')
            . '&ver=' . urlencode($pluginVersion);
        ?>

      <!-- BEGIN Ingenious Partner Marketing Platform – pageimpression tag version '<?php print esc_js($pluginVersion); ?>' -->
      <script type="text/javascript">
        (function () {
          window.itsClickPI = JSON.parse('<?php
              // sanity-check has been done usíng wp_json_encode
              print $itsClickPI;
              ?>');

          // @formatter:off
          var en = function(v) {if (v) {if (typeof(encodeURIComponent) == 'function') {return (encodeURIComponent(v));}return (escape(v));}};var ts = function() {var d = new Date();var t = d.getTime();return (t);};var im = function(s) {if (document.images) {if (typeof(ia) != 'object') {var ia = new Array();}var i = ia.length;ia[i] = new Image();ia[i].src = s;ia[i].onload = function() {};} else {document.write('<img src="' + s + '" height="1" width="1" border="0" alt="" style="display:none;">');}};var pr = 'https:';var cp = function(o) {var v = 'tst=' + ts();if (o.admCode) {v += '&amc=' + en(o.admCode);}if (o.subCode) {v += '&smc=' + en(o.subCode);}if (o.siteId) {v += '&sid=' + en(o.siteId);}if (o.referrer) {v += '&rrf=' + en(o.referrer);}if (o.locationHref) {v += '&hrf=' + en(o.locationHref);}v += '&ver='+en('<?php print esc_js($pluginVersion); ?>');if (o.paramRef) {v += '&prf=' + en(o.paramRef);}if (o.userVal1) {v += '&uv1=' + en(o.userVal1);}if (o.userVal2) {v += '&uv2=' + en(o.userVal2);}if (o.productId) { v += '&pid=' + en(o.productId);}if(o.custId){v+='&csi='+en(o.custId);} v += '&rmd=0';var s = (screen.width) ? screen.width : '0';s += 'X';s += (screen.height) ? screen.height : '0';s += 'X';s += (screen.colorDepth) ? screen.colorDepth : '0';v += '&scr=' + s;v += '&nck=';v += (navigator.cookieEnabled) ? navigator.cookieEnabled : 'null';v += '&njv=';v += (navigator.javaEnabled()) ? navigator.javaEnabled() : 'null';if (o.gdpr) {v += '&gdpr=' + en(o.gdpr);}if (o.gdprConsent) {v += '&gdpr_consent=' + en(o.gdprConsent);}return (v);};var itsStartCPI = function(o) {var s = pr + '//' + o.trcDomain + '/ts/' + o.advId + '/tsc?' + cp(o);im(s);};itsStartCPI(itsClickPI);
          var a = document.createElement('script'); a.type = 'text/javascript'; a.async = true; a.src = 'https://'+itsClickPI.trcDomain+'/scripts/ts/'+itsClickPI.advId+'contC.js'; var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(a, s);
          // @formatter:on
        })();
      </script>

      <noscript>
        <img src="<?php print esc_url($imageUrl); ?>" width="1" height="1" style="display:none;"
             alt="">
      </noscript>

      <!-- END Ingenious Partner Marketing Platform – pageimpression tag version '<?php print esc_js($pluginVersion); ?>' -->
        <?php
    }

    /**
     * @return false|string[]
     */
    private function getClickIdsFromCookie()
    {
        $clickIds = [];
        if (isset($_COOKIE[$this->cookieName])) {
            $existingCookie = sanitize_text_field($_COOKIE[$this->cookieName]);
            $clickIds = explode(",", $existingCookie);
        }
        return $clickIds;
    }
}
