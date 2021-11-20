<?php

require(__DIR__ . '/protected/vendor/autoload.php');
require(__DIR__ . '/protected/vendor/yiisoft/yii2/Yii.php');

/*

# Add this setting on /protected/config/common.php

  'params' => [
    'cdn_redirect' => [
      "cdn_domain" => "media.YOUR.ALTERNATE.DOMAIN-NAMES.com",
      "path_private_key" => "/fill/path/to/private-key.pem",
      "aws_cloudfront_keypair_id" => "KEY-GROUP-ID-ON-CLOUDFRONT",
      "cookie_domain" => "myhumhub.example.com",
    ]
  ]

*/

//
main();
exit;


// ---- ---- ---- ---- ---- ---- ---- ---- ---- ---- ----

//Main
function main()
{
  //Receive GET argument
  $arg = arg_receive();
  if (isset($ret['error'])) {
    header("HTTP/1.1 404 Not Found");
    exit;
  }
  $config = getConfig();

  //checkPrivilege, except profile icon or profile banner
  if (($arg['c'] != "pf") && ($arg['c'] != "pb")) {
    //
    $status = checkPrivilege($arg['g'], $config);
    if (!$status['flag']) {
      header("HTTP/1.1 404 Not Found");
      exit;
    }
  }

  //Redirect
  $res_forward = forward($arg, $config);
  if (!$res_forward) {
    header("HTTP/1.1 404 Not Found");
    exit;
  }
  return;
}

// ---- ---- ---- ---- ---- ---- ---- ---- ---- ---- ----

//Parse GET variables
function arg_receive()
{
  $ret = [];

  //command
  switch ($_GET['c']) {
    case "pv":
    case "dl":
    case "pf":
    case "pb":
      $ret['c'] = $_GET['c'];
      break;
    default:
      $ret['error'] = true;
      return $ret;
  }

  //guid
  if (!isset($_GET['g'])) {
    $ret['error'] = true;
    return $ret;
  } elseif (preg_match("#[0-9a-f]{32}#", str_replace("-", "", $_GET['g'])) === false) {
    $ret['error'] = true;
    return $ret;
  } else {
    $ret['g'] = $_GET['g'];
  }

  //extension
  $ret['e'] = preg_replace("#[^a-z]#", "", $_GET['e']);
  //filename
  $ret['f'] = "";
  if (isset($_GET['f'])) {
    $ret['f'] = trim(mb_ereg_replace("#[^\w\.]#", "_", $_GET['f']));
  }

  return $ret;
}

//Get Humhub configulation
function getConfig()
{
  $config = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/protected/humhub/config/common.php'),
    require(__DIR__ . '/protected/humhub/config/web.php'),
    (is_readable(__DIR__ . '/protected/config/dynamic.php')) ? require(__DIR__ . '/protected/config/dynamic.php') : [],
    require(__DIR__ . '/protected/config/common.php'),
    require(__DIR__ . '/protected/config/web.php')
  );

  if (!isset($config['params']['cdn_redirect'])) {
    print "Set config values at 'params' => [ 'cdn_redirect' => [] ] on /protected/config/common.php";
    exit;
  }
  return $config;
}

//Check whether browser has a proper privilege
function checkPrivilege($guid, $config)
{
  $ret = [
    'flag' => false,
    'log' => []
  ];

  // get session & user ID (when logged in)
  $app = new humhub\components\Application($config);
  $session = $app->session;
  $user_id = (isset($session['__id'])) ? $session['__id'] : -1;

  // convert guid -> id
  $file_obj = Yii::$app->db
    ->createCommand('SELECT * FROM `file` WHERE `guid` = :guid')
    ->bindValue(':guid', $guid)
    ->queryOne();
  if ($file_obj === false) {
    $ret['log'][] = "FILE NOT FOUND";
    $ret['log'][] = "NG";
    return $ret;
  }

  // getPolymorphicRelation
  $object = cp_loadActiveRecord($file_obj['object_model'], $file_obj['object_id']);
  //
  if ($object instanceof \yii\db\ActiveRecord) { //|| $object->asa($instance) !== null
    $ret['log'][] = "Valid ActiveRecord";
  } else {
    $ret['log'][] = "INVALID ActiveRecord OBJECT";
    $ret['log'][] = "NG";
    return $ret;
  }

  $can_read = cp_canRead($object, $user_id);
  $ret['log'][] = $can_read;
  if ($can_read != "forbidden") {
    $ret['log'][] = "OK";
    $ret['flag'] = true;
  } else {
    $ret['log'][] = "NG";
  }
  //
  return $ret;
}

/* File::canRead():
    Cited from: /protected/humhub/modules/file/models/File.php
        - canRead()
*/
function cp_canRead($object, $user_id)
{
  if (
    $object !== null &&
    ($object instanceof humhub\modules\content\components\ContentActiveRecord ||
      $object instanceof humhub\modules\content\components\ContentAddonActiveRecord)
  ) {
    $can_view = $object->content->canView($user_id);
    if ($can_view) {
      return "granted";
    } else {
      return "forbidden";
    }
  }

  return "public";
}

/* util: load ActiveRecord
    Cited from: /protected/humhub/components/behaviors/PolymorphicRelation.php
        - loadActiveRecord()
        - getPolymorphicRelation()
*/
function cp_loadActiveRecord($className, $primaryKey)
{
  $primaryKeyNames = $className::primaryKey();
  if (count($primaryKeyNames) !== 1) {
    Yii::error('Could not load polymorphic relation! Only one primary key is supported!');
    return null;
  }
  return $className::findOne([$primaryKeyNames[0] => $primaryKey]);
}


//Redirect
function forward($arg, $config)
{
  $cdn_domain = "media.ff.currentdir.com";
  $url_raw = "";

  # /r.php?c=pv&g=$arg_guid
  #return 302 https://$CDN_DOMAIN/file/$guid_1/$guid_2/$arg_guid/preview-image;
  if ($arg['c'] == "pv") {
    $url_raw = "https://{$cdn_domain}/file/" . substr($arg['g'], 0, 1) . "/" . substr($arg['g'], 1, 1) . "/{$arg['g']}/preview-image";
  }

  # /r.php?c=dl&g=$arg_guid
  #return 302 https://$CDN_DOMAIN/file/$guid_1/$guid_2/$arg_guid/file;
  if ($arg['c'] == "dl") {
    $url_raw = "https://{$cdn_domain}/file/" . substr($arg['g'], 0, 1) . "/" . substr($arg['g'], 1, 1) . "/{$arg['g']}/file";
  }

  # /r.php?c=pf&g=$arg_guid
  #rewrite ^/.*?profile_image\/([0-9a-f\-]+)(\.[a-z]+).*?$ https://$CDN_DOMAIN/profile_image/$1$2 break;
  if ($arg['c'] == "pf") {
    $url_raw = "https://{$cdn_domain}/profile_image/{$arg['g']}.{$arg['e']}";
  }

  # /r.php?c=pb&g=$arg_guid
  #rewrite ^/.*?profile_image\/banner\/([0-9a-f\-]+)(\.[a-z]+).*?$ https://$CDN_DOMAIN/profile_image/banner/$1$2 break;
  if ($arg['c'] == "pb") {
    $url_raw = "https://{$cdn_domain}/profile_image/banner/{$arg['g']}.{$arg['e']}";
  }

  if ($url_raw == "") {
    return false;
  }

  # Add sign, then redirect using "Location: " header
  $cf = new CloudFrontSignedURL();
  $sign = $cf->registerSignedURL();
  $url_signed = "{$url_raw}?a=1&Policy={$sign['p']}&Signature={$sign['s']}&Key-Pair-Id={$sign['k']}";
  $url_signed = ($arg['f'] != "") ? $url_signed . "&filename=" . $arg['f'] : $url_signed;
  header("Location: {$url_signed}");
  //print $url_signed; //"<a href='{$url_signed}' target='_blank'>open</a>";
  return true;
}


//Issue AWS CloudFront SignedURL
class CloudFrontSignedURL
{
  var $pref;

  //Config
  public function __construct($config)
  {
    $_cdn = $config['params']['cdn_redirect'];
    $this->pref = $_cdn;
    /* example: 
    [
      "cdn_domain" => "media.YOUR.ALTERNATE.DOMAIN-NAMES.com",
      "path_private_key" => "/fill/path/to/private-key.pem",
      "aws_cloudfront_keypair_id" => "KEY-GROUP-ID-ON-CLOUDFRONT",
      "cookie_domain" => "myhumhub.example.com",
    ];
    */
  }

  //Generate sign for CloudFront
  public function registerSignedURL(): array
  {
    //policy
    $date_greater_than = time() - 60 * 3; //valid from 3 min before
    $date_less_than = time() + 60 * 3; //... to 3 min after
    $policy_0 = [
      "Resource" => "https://" . $this->pref['cdn_domain'] . "/*",
      "Condition" => [
        "DateGreaterThan" => [
          "AWS:EpochTime" => $date_greater_than
        ],
        "DateLessThan" => [
          "AWS:EpochTime" => $date_less_than
        ],
      ]
    ];
    $policy = ["Statement" => [0 => $policy_0]];
    $policy_json = json_encode($policy, JSON_INVALID_UTF8_IGNORE);
    // URL-safe base64 encode
    $policy_json_b64 = $this->url_safe_base64_encode($policy_json);

    //Encode the policy with private key
    $pkeyid = openssl_pkey_get_private(
      join("", file(
        $this->pref['path_private_key']
      ))
    );
    openssl_sign($policy_json, $policy_signed, $pkeyid);
    unset($pkeyid);
    // URL-safe base64 encode
    $policy_signed_b64 = $this->url_safe_base64_encode($policy_signed);

    //
    return [
      'p' => $policy_json_b64,
      's' => $policy_signed_b64,
      'k' => $this->pref['aws_cloudfront_keypair_id']
    ];
  }
  public function url_safe_base64_encode($value)
  {
    $encoded = base64_encode($value);
    return str_replace(array('+', '=', '/'), array('-', '_', '~'), $encoded);
  }
}
