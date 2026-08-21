<?php
class Validation
{
  public static function validateName($name, $fieldName)
  {
    $errors = [];

    if (empty($name)) {
      return ["$fieldName is required"];
    }

    // Remove double spaces and trim
    $name = preg_replace('/\s+/', ' ', trim($name));

    // Check for numbers followed by letters or letters followed by numbers
    if (preg_match('/\d[a-zA-Z]|[a-zA-Z]\d/', $name)) {
      $errors[] = "Numbers and letters cannot be mixed together in $fieldName";
    }

    // Check for double spaces (should not exist after cleaning)
    if (strpos($name, '  ') !== false) {
      $errors[] = "Double spaces are not allowed in $fieldName";
    }

    // Check for three consecutive identical letters (case-insensitive)
    if (preg_match('/([a-zA-Z])\1\1/i', $name)) {
      $errors[] = "Three consecutive identical letters are not allowed in $fieldName";
    }

    // Check if all capital letters
    if (preg_match('/^[A-Z\s]+$/', $name)) {
      $errors[] = "All capital letters are not allowed in $fieldName";
    }

    // Check first letter is capital
    if (!preg_match('/^[A-Z]/', $name)) {
      $errors[] = "First letter of $fieldName must be capital";
    }

    // Check rest of letters should be lowercase and proper format
    $words = explode(' ', $name);
    foreach ($words as $word) {
      if (!preg_match('/^[A-Z][a-z]*$/', $word)) {
        $errors[] = "Each word in $fieldName must start with capital letter followed by lowercase";
        break;
      }
    }

    // No special characters or numbers
    if (preg_match('/[^a-zA-Z\s]/', $name)) {
      $errors[] = "Special characters and numbers are not allowed in $fieldName";
    }

    return $errors;
  }

  public static function validatePasswordStrength($password)
  {
    $strength = 0;
    $feedback = [];

    if (strlen($password) >= 8)
      $strength++;
    else
      $feedback[] = "Password should be at least 8 characters";

    if (preg_match('/[A-Z]/', $password))
      $strength++;
    else
      $feedback[] = "Include at least one uppercase letter";

    if (preg_match('/[a-z]/', $password))
      $strength++;
    else
      $feedback[] = "Include at least one lowercase letter";

    if (preg_match('/[0-9]/', $password))
      $strength++;
    else
      $feedback[] = "Include at least one number";

    if (preg_match('/[^a-zA-Z0-9]/', $password))
      $strength++;
    else
      $feedback[] = "Include at least one special character";

    if ($strength >= 4)
      return ['strength' => 'strong', 'feedback' => []];
    if ($strength >= 3)
      return ['strength' => 'medium', 'feedback' => $feedback];
    return ['strength' => 'weak', 'feedback' => $feedback];
  }

  public static function validateAge($birthdate)
  {
    $today = new DateTime();
    $birthdate = new DateTime($birthdate);
    $age = $today->diff($birthdate)->y;

    if ($age < 18) {
      return ["Must be at least 18 years old"];
    }
    return [];
  }

  public static function validateIDFormat($id)
  {
    if (!preg_match('/^\d{4}-\d{4}$/', $id)) {
      return ["ID must be in format xxxx-xxxx (numbers only)"];
    }
    return [];
  }

  public static function validateEmail($email)
  {
    if (empty($email)) {
      return ["Email is required"];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return ["Please enter a valid email address"];
    }

    return [];
  }

  public static function validateExtensionName($extension)
  {
    if (empty($extension)) {
      return []; // Optional field
    }

    $errors = [];
    $validExtensions = ['jr', 'sr', 'i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x'];
    $normalized = strtolower(str_replace('.', '', trim($extension)));

    if (!in_array($normalized, $validExtensions)) {
      $errors[] = "Extension must be like Jr., Sr., I, II, III, IV, V, etc.";
    }

    return $errors;
  }

  public static function validateAddressField($value, $fieldName)
  {
    $errors = [];

    if (empty($value)) {
      return ["$fieldName is required"];
    }

    // Basic validation - no special characters except common address characters
    if (preg_match('/[<>{}[\]$%^&*()+|~=`]/', $value)) {
      $errors[] = "Special characters are not allowed in $fieldName";
    }

    return $errors;
  }

  public static function validateZipCode($zip_code)
  {
    if (empty($zip_code)) {
      return ["Zip Code is required"];
    }

    if (!preg_match('/^\d+$/', $zip_code)) {
      return ["Zip code must contain numbers only"];
    }

    if (strlen($zip_code) < 4 || strlen($zip_code) > 10) {
      return ["Zip code must be 4-10 digits"];
    }

    return [];
  }

  public static function validateUsername($username)
  {
    if (empty($username)) {
      return ["Username is required"];
    }

    if (strlen($username) < 3 || strlen($username) > 20) {
      return ["Username must be 3-20 characters long"];
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
      return ["Username can only contain letters, numbers, and underscores"];
    }

    return [];
  }
}
?>