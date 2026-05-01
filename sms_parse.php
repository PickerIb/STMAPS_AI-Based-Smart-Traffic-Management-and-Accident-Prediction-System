<?php
/*
*	HDEV SMS Gateway 
*	@email :  info@hdevtech.cloud
*	@link : https://sms-api.hdev.rw
*
*/

/*
	Master SMS controller
*/
if (!defined('hdev_sms')) {
  class hdev_sms
  {
    private static $api_id = null;
    private static $api_key = null;
    public static function  api_key($value='')
    {
      self::$api_key = $value;
    }
    public static function api_id($value='')
    {
      self::$api_id = $value;
    }
    public static function send($sender_id,$tel,$message,$link=''){
      $curl = curl_init();

      curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://sms-api.hdev.rw/api_pay/api/'.self::$api_id.'/'.self::$api_key,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('ref'=>'sms','sender_id' => $sender_id,'tel' => $tel,'message' => $message,'link'=>$link),
      ));

      $response = curl_exec($curl);

      curl_close($curl);
      return json_decode($response);
    }
    public static function topup($tel,$amount,$transaction_ref="",$link=''){
      $transaction_ref = "HDEVSMS-".time().rand(100000,999999);
      $curl = curl_init();

      curl_setopt_array($curl, array(
         CURLOPT_URL => 'https://sms-api.hdev.rw/api_pay/api/'.self::$api_id.'/'.self::$api_key,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('ref'=>'pay','tel' => $tel,'tx_ref' => $transaction_ref,'amount' => $amount,'link'=>$link),
      ));

      $response = curl_exec($curl);

      curl_close($curl);
      return json_decode($response);
    }
    public static function get_topup($tx_ref='')
    {
      $curl = curl_init();

      curl_setopt_array($curl, array(
         CURLOPT_URL => 'https://sms-api.hdev.rw/api_pay/api/'.self::$api_id.'/'.self::$api_key,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('ref'=>'read','tx_ref' => $tx_ref),
      ));

      $response = curl_exec($curl);

      curl_close($curl);
      return json_decode($response);
    }
  }

}
?>
