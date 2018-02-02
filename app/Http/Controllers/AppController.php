<?php

namespace App\Http\Controllers;

use App\Respond\Models\Site;

use \Illuminate\Http\Request;

use App\Respond\Libraries\Utilities;

class AppController extends Controller
{

  /**
   * Settings
   *
   * @return Response
   */
  public function settings(Request $request)
  {

    $has_passcode = true;

    if(env('PASSCODE') === '') {
      $has_passcode = false;
    }

    $language = true;

    if(env('DEFAULT_LANGUAGE') != NULL) {
      $language = env('DEFAULT_LANGUAGE');
    }

    // determine if the mode is PRO or CORE
    if(file_exists( app()->basePath('app/Pro/routes.php' ))) {
      $mode = 'pro';
    }
    else {
      $mode = 'core';
    }

    // return app settings
    $settings = array(
      'mode' => $mode,
      'hasPasscode' => $has_passcode,
      'siteUrl' => Utilities::retrieveSiteURL(),
      'logoUrl' => env('LOGO_URL'),
      'themesLocation' => env('THEMES_LOCATION'),
      'primaryColor' => env('PRIMARY_COLOR'),
      'primaryDarkColor' => env('PRIMARY_DARK_COLOR'),
      'usesLDAP' => !empty(env('LDAP_SERVER')),
      'activationMethod' => env('ACTIVATION_METHOD'),
      'activationUrl' => env('ACTIVATION_URL'),
      'stripeAmount' => env('STRIPE_AMOUNT', ''),
      'stripeCurrency' => env('STRIPE_CURRENCY', ''),
      'stripeName' => env('STRIPE_NAME', ''),
      'stripeDescription' => env('STRIPE_DESCRIPTION', ''),
      'stripePublishableKey' => env('STRIPE_PUBLISHABLE_KEY', ''),
      'recaptchaSiteKey' => env('RECAPTCHA_SITE_KEY', ''),
      'acknowledgement' => env('ACKNOWLEDGEMENT', ''),
      'defaultLanguage' => $language
    );

    return response()->json($settings);

  }

  /**
   * Lists the themes available for the app
   *
   * @return Response
   */
  public function listThemes(Request $request)
  {

    // list pages in the site
    $dir = app()->basePath().'/public/'.env('THEMES_LOCATION');

    // list files
    $arr = Utilities::listSpecificFiles($dir, 'theme.json');

    $result = array();

    foreach ($arr as $item) {

      // get contents of file
      $json = json_decode(file_get_contents($item));

      // get location of theme
      $temp = explode(getenv('THEMES_LOCATION'), $item);
      $location = substr($temp[1], 0, strpos($temp[1], '/theme.json'));

      $json->location = $location;

      array_push($result, $json);

    }

    return response()->json($result);

  }

  /**
   * Lists the languages available for the app
   *
   * @return Response
   */
  public function listLanguages(Request $request)
  {

    // list pages in the site
    $file = app()->basePath().'/public/assets/i18n/languages.json';

    $result = array();

    if(file_exists($file)) {

      $json = file_get_contents($file);
      $result = json_decode($json);

    }

    return response()->json($result);

  }

  /**
   * Listen for Stripe Webhooks, ref: https://stripe.com/docs/webhooks and https://stripe.com/docs/api#event_types
   *
   * @return Response
   */
  public function listenForStripeWebhooks(Request $request)
  {

    \Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

    // get event
    $event = $request->json()->all();

    $type = $event['type'];
    $customer = $event['data']['object']['customer'];

    // handle event types
    if($type == 'charge.failed' || $type == 'invoice.payment_failed') {

      if($customer != NULL) {

        $site = Site::getSiteByCustomerId($customer);

        if($site != NULL) {

          $site->status = 'Active';

          // send email
          $to = $site->email;
          $from = env('EMAILS_FROM');
          $fromName = env('EMAILS_FROM_NAME');
          $subject = env('SUCCESSFUL_CHARGE_SUBJECT', 'Successful Charge');
          $file = app()->basePath().'/resources/emails/failed-charge.html';

          $replace = array(
            '{{brand}}' => env('BRAND'),
            '{{reply-to}}' => env('EMAILS_FROM')
          );

          // send email from file
          Utilities::sendEmailFromFile($to, $from, $fromName, $subject, $replace, $file);
        }
        else {
          return response('Site not found', 401);
        }

      }
      else {
        return response('Customer not found', 401);
      }

    }
    else if($type == 'charge.succeeded' || $type == 'invoice.payment_succeeded') {

      if($customer != NULL) {

        $site = Site::getSiteByCustomerId($customer);

        if($site != NULL) {

          $site->status = 'Active';

          // send email
          $to = $site->email;
          $from = env('EMAILS_FROM');
          $fromName = env('EMAILS_FROM_NAME');
          $subject = env('SUCCESSFUL_CHARGE_SUBJECT', 'Successful Charge');
          $file = app()->basePath().'/resources/emails/successful-charge.html';

          $replace = array(
            '{{brand}}' => env('BRAND'),
            '{{reply-to}}' => env('EMAILS_FROM')
          );

          // send email from file
          Utilities::sendEmailFromFile($to, $from, $fromName, $subject, $replace, $file);
        }
        else {
          return response('Site not found', 401);
        }

      }
      else {
        return response('Customer not found', 401);
      }

    }

    return response('Ok', 200);

  }


}
