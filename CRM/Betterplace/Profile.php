<?php
/*------------------------------------------------------------+
| SYSTOPIA betterplace.org Spendenformular Direkt Integration |
| Copyright (C) 2017 SYSTOPIA                                 |
| Author: B. Endres (endres@systopia.de)                      |
|         J. Schuppe (schuppe@systopia.de)                    |
+-------------------------------------------------------------+
| This program is released as free software under the         |
| Affero GPL license. You can redistribute it and/or          |
| modify it under the terms of this license which you         |
| can read by viewing the included agpl.txt or online         |
| at www.gnu.org/licenses/agpl.html. Removal of this          |
| copyright header is strictly prohibited without             |
| written permission from the original author(s).             |
+-------------------------------------------------------------*/

use CRM_Betterplace_ExtensionUtil as E;

/**
 * Profiles define how incoming submissions from the BPDonation API are
 * processed in CiviCRM.
 */
class CRM_Betterplace_Profile {

  /**
   * @var CRM_Betterplace_Profile[] $_profiles
   *   Caches the profile objects.
   */
  protected static $_profiles = NULL;

  /**
   * @var string $name
   *   The name of the profile.
   */
  protected $name = NULL;

  /**
   * @var array $data
   *   The properties of the profile.
   */
  protected $data = NULL;

  /**
   * CRM_Betterplace_Profile constructor.
   *
   * @param string $name
   *   The name of the profile.
   * @param array $data
   *   The properties of the profile
   */
  public function __construct($name, $data) {
    $this->name = $name;
    $allowed_attributes = self::allowedAttributes();
    $this->data = $data + array_combine(
        $allowed_attributes,
        array_fill(0, count($allowed_attributes), NULL)
      );
  }


  /**
   * Checks whether the profile's selector matches the given form ID.
   *
   * @param string | int $form_id
   *
   * @return bool
   */
  public function matches($form_id) {
    $selector = $this->getAttribute('selector');
    $form_ids = explode(',', $selector);
    return in_array($form_id, $form_ids);
  }

  /**
   * Retrieves all data attributes of the profile.
   *
   * @return array
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Retrieves the profile name.
   *
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Sets the profile name.
   *
   * @param $name
   */
  public function setName($name) {
    $this->name = $name;
  }

  /**
   * Retrieves an attribute of the profile.
   *
   * @param string $attribute_name
   *
   * @return mixed | NULL
   */
  public function getAttribute($attribute_name) {
    if (isset($this->data[$attribute_name])) {
      return $this->data[$attribute_name];
    }
    else {
      return NULL;
    }
  }

  /**
   * Sets an attribute of the profile.
   *
   * @param string $attribute_name
   * @param mixed $value
   *
   * @throws \Exception
   *   When the attribute name is not known.
   */
  public function setAttribute($attribute_name, $value) {
    if (!in_array($attribute_name, self::allowedAttributes())) {
      throw new Exception("Unknown attribute {$attribute_name}.");
    }
    // TODO: Check if value is acceptable.
    $this->data[$attribute_name] = $value;
  }

  /**
   * Verifies whether the profile is valid (i.e. consistent and not colliding
   * with other profiles).
   *
   * @throws Exception
   *   When the profile could not be successfully validated.
   */
  public function verifyProfile() {
    // TODO: check
    //  data of this profile consistent?
    //  conflicts with other profiles?
  }

  /**
   * Persists the profile within the CiviCRM settings.
   */
  public function saveProfile() {
    self::$_profiles[$this->getName()] = $this;
    $this->verifyProfile();
    self::storeProfiles();
  }

  /**
   * Deletes the profile from the CiviCRM settings.
   */
  public function deleteProfile() {
    unset(self::$_profiles[$this->getName()]);
    self::storeProfiles();
  }

  /**
   * Returns an array of attributes allowed for a profile.
   *
   * @return array
   */
  public static function allowedAttributes() {
    return array(
      'selector',
      'location_type_id',
      'financial_type_id',
      'campaign_id',
      'pi_creditcard',
      'pi_sepa',
      'pi_paypal',
      'groups',
    );
  }

  /**
   * Returns the default profile with "factory" defaults.
   *
   * @param string $name
   *   The profile name. Defaults to "default".
   *
   * @return CRM_Betterplace_Profile
   */
  public static function createDefaultProfile($name = 'default') {
    return new CRM_Betterplace_Profile($name, array(
      'selector'          => '',
      'location_type_id'  => CRM_Betterplace_Submission::LOCATION_TYPE_ID_WORK,
      'financial_type_id' => 1, // "Donation"
      'campaign_id'       => '',
      'pi_creditcard'     => 1, // "Credit Card"
      'pi_sepa'           => 5, // "EFT"
      'pi_paypal'         => 3, // "Debit"
      'groups'            => '',
    ));
  }

  /**
   * Retrieves the profile that matches the given form ID, i.e. the profile
   * which is responsible for processing the form's data.
   * Returns the default profile if no match was found.
   *
   * @param $form_id
   *
   * @return CRM_Betterplace_Profile
   */
  public static function getProfileForForm($form_id) {
    $profiles = self::getProfiles();
    foreach ($profiles as $profile) {
      if ($profile->matches($form_id)) {
        return $profile;
      }
    }

    // No profile matched, return default profile.
    return $profiles['default'];
  }

  /**
   * Retrieves the profil with the given name.
   *
   * @param $name
   *
   * @return CRM_Betterplace_Profile | NULL
   */
  public static function getProfile($name) {
    $profiles = self::getProfiles();
    if (isset($profiles[$name])) {
      return $profiles[$name];
    }
    else {
      return NULL;
    }
  }

  /**
   * Retrieves the list of all profiles persisted within the current CiviCRM
   * settings, including the default profile.
   *
   * @return CRM_Betterplace_Profile[]
   */
  public static function getProfiles() {
    if (self::$_profiles === NULL) {
      self::$_profiles = array();
      if ($profiles_data = CRM_Core_BAO_Setting::getItem('de.systopia.betterplace', 'betterplace_profiles')) {
        foreach ($profiles_data as $profile_name => $profile_data) {
          self::$_profiles[$profile_name] = new CRM_Betterplace_Profile($profile_name, $profile_data);
        }
      }
    }

    // Include the default profile if it was not overridden within the settings.
    if (!isset(self::$_profiles['default'])) {
      self::$_profiles['default'] = self::createDefaultProfile();
      self::storeProfiles();
    }

    return self::$_profiles;
  }


  /**
   * Persists the list of profiles into the CiviCRM settings.
   */
  public static function storeProfiles() {
    $profile_data = array();
    foreach (self::$_profiles as $profile_name => $profile) {
      $profile_data[$profile_name] = $profile->data;
    }
    CRM_Core_BAO_Setting::setItem($profile_data, 'de.systopia.betterplace', 'betterplace_profiles');
  }
}
