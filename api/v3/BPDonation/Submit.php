<?php
/*-------------------------------------------------------+
| SYSTOPIA Betterplace Integration                       |
| Copyright (C) 2017 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
|         J. Schuppe (schuppe@systopia.de)               |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

function civicrm_api3_b_p_donation_submit($params) {
  // Get the profile defined for the given form ID, or the default profile if
  // none matches.
  $profile = CRM_Betterplace_Profile::getProfileForForm($params['form_id']);

  // Exclude address for now, as we are checking organisation address first.
  if (!empty($params['organization_name'])) {
    $submitted_address = array();
    foreach (array('street_address', 'postal_code', 'city', 'country') as $address_component) {
      if (!empty($params[$address_component])) {
        $submitted_address[$address_component] = $params[$address_component];
        unset($params[$address_component]);
      }
    }
  }
  // Get the ID of the contact matching the given contact data, or create a new
  // contact if none exists for the given contact data.
  if (!$contact_id = CRM_Betterplace_Submission::getContact('Individual', $params)) {
    return civicrm_api3_create_error('Individual contact could not be found or created.');
  }

  // Organisation lookup.
  if (!empty($params['organization_name'])) {
    if (!empty($submitted_address)) {
      $params += $submitted_address;
    }
    if (!$organisation_id = CRM_Betterplace_Submission::getContact('Organization', $params)) {
      return civicrm_api3_create_error('Organisation contact could not be found or created.');
    }
  }
  $address_shared = isset($organisation_id) && CRM_Betterplace_Submission::shareWorkAddress($contact_id, $organisation_id);

  // Address is not shared, use submitted address.
  if (!$address_shared && !empty($submitted_address)) {
    $submitted_address['contact_id'] = $contact_id;
    $submitted_address['location_type_id'] = CRM_Betterplace_Submission::LOCATION_TYPE_ID_WORK;
    civicrm_api3('Address', 'create', $submitted_address);
  }

  // Get the payment instrument defined within the profile, or return an error
  // if none matches (i.e. an unknown payment method was submitted).
  if (!$payment_instrument_id = $profile->getAttribute('pi_' . $params['payment_method'])) {
    return civicrm_api3_create_error('Payment method could not be matched to existing payment instrument.');
  }

  // Create contribution.
  $contribution_data = array(
    'financial_type_id' => $profile->getAttribute('financial_type_id'),
    'contact_id' => $contact_id,
    'payment_instrument_id' => $payment_instrument_id,
    'total_amount' => $params['amount_in_cents'] / 100,
  );
  if (!empty($params['donation_id'])) {
    $contribution_data['trxn_id'] = $params['donation_id'];
  }
  if (isset($params['time'])) {
    $contribution_data['receive_date'] = date('YmdH:i:s', $params['time']);
  }
  // Add campaign relationship if defined in the profile.
  if (!empty($campaign_id = $profile->getAttribute('campaign_id'))) {
    $contribution_data['campaign_id'] = $campaign_id;
  }
  if (!$contribution = civicrm_api3('Contribution', 'create', $contribution_data)) {
    civicrm_api3_create_error('Contribution could not be created.');
  }

  // If requested, add contact to the groups defined in the profile.
  if (!empty($params['newsletter']) && !empty($groups = $profile->getAttribute('groups'))) {
    foreach ($groups as $group_id) {
      civicrm_api3('GroupContact', 'create', array(
        'group_id' => $group_id,
        'contact_id' => $contact_id,
      ));
    }
  }

  return civicrm_api3_create_success($contribution, $params, NULL, NULL, $foo = NULL, array(
    'foreign_id' => $contribution['id'],
  ));
}

function _civicrm_api3_b_p_donation_submit_spec(&$params) {
  $params['form_id'] = array(
    'name'         => 'form_id',
    'title'        => 'Betterplace form ID',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => 'ID of the Betterplace form.',
  );
  $params['first_name'] = array(
    'name'         => 'first_name',
    'title'        => 'First Name',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'The contact\'s first name.',
  );
  $params['last_name'] = array(
    'name'         => 'last_name',
    'title'        => 'Last Name',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'The contact\'s last name.',
  );
  $params['email'] = array(
    'name'         => 'email',
    'title'        => 'Email',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => 'The contact\'s email.',
  );
  $params['street_address'] = array(
    'name'         => 'street',
    'title'        => 'Street address',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'The contact\'s street_address.',
  );
  $params['postal_code'] = array(
    'name'         => 'zip',
    'title'        => 'Postal / ZIP code',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'The contact\'s postal_code.',
  );
  $params['city'] = array(
    'name'         => 'city',
    'title'        => 'City',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'The contact\'s city.',
  );
  $params['country'] = array(
    'name'         => 'country',
    'title'        => 'Country',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'The contact\'s country.',
  );
  $params['organization_name'] = array(
    'name'         => 'company_name',
    'title'        => 'Company name',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'The organisation\'s name.',
  );
  $params['donation_id'] = array(
    'name'         => 'donation_id',
    'title'        => 'Donation ID',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'The donation ID.',
  );
  $params['time'] = array(
    'name'         => 'time',
    'title'        => 'Time',
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => 'A timestamp when the donation was issued.',
  );
  $params['amount_in_cents'] = array(
    'name'         => 'amount_in_cents',
    'title'        => 'Amount (in cents)',
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 1,
    'description'  => 'The donation amount in Euro cents.',
  );
  $params['payment_method'] = array(
    'name'         => 'payment_method',
    'title'        => 'Payment method',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => 'The payment method used for the donation.',
  );
  $params['campaign'] = array(
    'name'         => 'campaign',
    'title'        => 'Campaign',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'Betterplace campaign.',
  );
  $params['newsletter'] = array(
    'name'         => 'newsletter',
    'title'        => 'Newsletter',
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => 'Whether to subbscribe the contact to the newsletter group defined in the profile.',
  );
  $params['webhook_id'] = array(
    'name'         => 'webhook_id',
    'title'        => 'Webhook ID',
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => 'Webhook ID.',
  );
}
