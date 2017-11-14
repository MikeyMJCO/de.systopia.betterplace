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

use CRM_Betterplace_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Betterplace_Form_Profile extends CRM_Core_Form {

  /**
   * @var CRM_Betterplace_Profile $profile
   *
   * The profile object the form is acting on.
   */
  protected $profile;

  /**
   * Builds the form structure.
   */
  public function buildQuickForm() {

    // Get the profile the form is acting on.
    if (!$profile_name = CRM_Utils_Request::retrieve('name', 'String', $this)) {
      if (CRM_Utils_Request::retrieve('new', 'Boolean', $this)) {
        $profile_name = NULL;
      }
      else {
        $profile_name = 'default';
      }
    }
    if (!$this->profile = CRM_Betterplace_Profile::getProfile($profile_name)) {
      $this->profile = new CRM_Betterplace_Profile(NULL, array());
      CRM_Utils_System::setTitle(E::ts('New betterplace.org Direkt API profile'));
    }
    else {
      CRM_Utils_System::setTitle(E::ts('Edit betterplace.org Direkt API profile <em>%1</em>', array(1 => $this->profile->getName())));
    }

    // add form elements
    $is_default = $this->profile->getName() == 'default';
    $this->add(
      ($is_default ? 'static' : 'text'),
      'name',
      E::ts('Profile name'),
      array(),
      !$is_default
    );

    $this->add(
      'text', // field type
      'selector', // field name
      E::ts('Form IDs'), // field label
      array(),
      TRUE // is required
    );

    $this->add(
      'select',
      'location_type_id',
      E::ts('Location type'),
      $this->getLocationTypes(),
      TRUE
    );

    $this->add(
      'select', // field type
      'financial_type_id', // field name
      E::ts('Financial Type'), // field label
      $this->getFinancialTypes(), // list of options
      TRUE // is required
    );

    $this->add(
      'select', // field type
      'campaign_id', // field name
      E::ts('Campaign'), // field label
      $this->getCampaigns(), // list of options
      FALSE // is not required
    );

    $this->add(
      'select', // field type
      'pi_creditcard', // field name
      E::ts('Record CreditCard as'), // field label
      $this->getPaymentInstruments(), // list of options
      TRUE // is required
    );

    $this->add(
      'select', // field type
      'pi_paypal', // field name
      E::ts('Record PayPal as'), // field label
      $this->getPaymentInstruments(), // list of options
      TRUE // is required
    );

    $this->add(
      'select', // field type
      'pi_sepa', // field name
      E::ts('Record SEPA direct debit as'), // field label
      $this->getPaymentInstruments(), // list of options
      TRUE // is required
    );

    $this->add(
      'select', // field type
      'groups', // field name
      E::ts('Sign up for groups'), // field label
      $this->getGroups(), // list of options
      FALSE, // is not required
      array('class' => 'crm-select2 huge', 'multiple' => 'multiple')
    );


    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE,
      ),
    ));

    // Export form elements.
    parent::buildQuickForm();
  }



  /**
   * Set the default values (i.e. the profile's current data) in the form.
   */
  public function setDefaultValues() {
    $defaults = parent::setDefaultValues();
    $defaults['name'] = $this->profile->getName();
    foreach ($this->profile->getData() as $element_name => $value) {
      $defaults[$element_name] = $value;
    }
    return $defaults;
  }


  /**
   * Store the values submitted with the form in the profile.
   */
  public function postProcess() {
    $values = $this->exportValues();
    $this->profile->setName($values['name']);
    foreach ($this->profile->getData() as $element_name => $value) {
      if (isset($values[$element_name])) {
        $this->profile->setAttribute($element_name, $values[$element_name]);
      }
    }
    $this->profile->saveProfile();
    parent::postProcess();
  }

  /**
   * Retrieves location types present within the system as options for select
   * form elements.
   */
  public function getLocationTypes() {
    $location_types = array();
    $query = civicrm_api3('LocationType', 'get', array(
      'is_active' => 1,
    ));
    foreach ($query['values'] as $type) {
      $location_types[$type['id']] = $type['name'];
    }

    return $location_types;
  }

  /**
   * Retrieve financial types present within the system as options for select
   * form elements.
   */
  public function getFinancialTypes() {
    $financial_types = array();
    $query = civicrm_api3('FinancialType', 'get', array(
      'is_active'    => 1,
      'option.limit' => 0,
      'return'       => 'id,name'
    ));
    foreach ($query['values'] as $type) {
      $financial_types[$type['id']] = $type['name'];
    }
    return $financial_types;
  }

  /**
   * Retrieve campaigns present within the system as options for select form
   * elements.
   */
  public function getCampaigns() {
    $campaigns = array('' => E::ts("no campaign"));
    $query = civicrm_api3('Campaign', 'get', array(
      'is_active'    => 1,
      'option.limit' => 0,
      'return'       => 'id,title'
    ));
    foreach ($query['values'] as $campaign) {
      $campaigns[$campaign['id']] = $campaign['title'];
    }
    return $campaigns;
  }

  /**
   * Retrieve payment instruments present within the system as options for
   * select form elements.
   */
  public function getPaymentInstruments() {
    // TODO: Cache, as these are retrieved for multiple select form elements.
    $payment_instruments = array();
    $query = civicrm_api3('OptionValue', 'get', array(
      'option_group_id' => 'payment_instrument',
      'TODO_is_active'  => 1,
      'option.limit'    => 0,
      'return'          => 'value,label'
    ));
    foreach ($query['values'] as $payment_instrument) {
      $payment_instruments[$payment_instrument['value']] = $payment_instrument['label'];
    }
    return $payment_instruments;
  }

  /**
   * Retrieves active groups used as mailing lists within the system as options
   * for select form elements.
   */
  public function getGroups() {
    $groups = array();
    $group_types = civicrm_api3('OptionValue', 'get', array(
      'option_group_id' => 'group_type',
      'name' => CRM_Betterplace_Submission::GROUP_TYPE_MAILING_LIST,
    ));
    if ($group_types['count'] > 0) {
      $group_type = reset($group_types['values']);
      $query = civicrm_api3('Group', 'get', array(
        'is_active' => 1,
        'group_type' => array('LIKE' => '%' . CRM_Utils_Array::implodePadded($group_type['value']) . '%'),
        'option.limit'   => 0,
        'return'         => 'id,name'
      ));
      foreach ($query['values'] as $group) {
        $groups[$group['id']] = $group['name'];
      }
    }
    else {
      $groups[''] = E::ts('No mailing lists available');
    }
    return $groups;
  }

}
