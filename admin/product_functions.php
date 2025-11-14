<?php
/**
 * product_functions.php
 * Contains utility functions for rendering form elements and validating product data
 */
function renderInput($name, $label, $type = 'text', $value = '', $required = false, $attributes = [])
{
    $req = $required ? 'required' : '';
    $attrs = '';
    foreach ($attributes as $key => $val) {
        $attrs .= " $key=\"$val\"";
    }
    $errorClass = isset($GLOBALS['errors'][$name]) ? 'error' : '';

    $html = "<div class='form-group'>";
    $html .= "<label>$label " . ($required ? '<span class="required">*</span>' : '') . "</label>";
    $html .= "<input type='$type' name='$name' value='" . htmlspecialchars($value) . "' $req $attrs class='$errorClass'>";
    if (isset($GLOBALS['errors'][$name])) {
        $html .= "<span class='error-message'>" . $GLOBALS['errors'][$name] . "</span>";
    }
    $html .= "</div>";
    return $html;
}

function renderTextarea($name, $label, $value = '', $required = false)
{
    $req = $required ? 'required' : '';
    $errorClass = isset($GLOBALS['errors'][$name]) ? 'error' : '';

    $html = "<div class='form-group'>";
    $html .= "<label>$label " . ($required ? '<span class="required">*</span>' : '') . "</label>";
    $html .= "<textarea name='$name' $req class='$errorClass'>" . htmlspecialchars($value) . "</textarea>";
    if (isset($GLOBALS['errors'][$name])) {
        $html .= "<span class='error-message'>" . $GLOBALS['errors'][$name] . "</span>";
    }
    $html .= "</div>";
    return $html;
}

function renderSelect($name, $label, $options, $selected = '', $required = false)
{
    $req = $required ? 'required' : '';
    $errorClass = isset($GLOBALS['errors'][$name]) ? 'error' : '';

    $html = "<div class='form-group'>";
    $html .= "<label>$label " . ($required ? '<span class="required">*</span>' : '') . "</label>";
    $html .= "<select name='$name' $req class='$errorClass'>";
    foreach ($options as $value => $text) {
        $sel = ($value == $selected) ? 'selected' : '';
        $html .= "<option value='$value' $sel>$text</option>";
    }
    $html .= "</select>";
    if (isset($GLOBALS['errors'][$name])) {
        $html .= "<span class='error-message'>" . $GLOBALS['errors'][$name] . "</span>";
    }
    $html .= "</div>";
    return $html;
}

function validateProduct($data, $isUpdate = false)
{
    $errors = [];

    if (empty(trim($data['name']))) {
        $errors['name'] = 'Product name is required';
    } elseif (strlen($data['name']) > 150) {
        $errors['name'] = 'Product name must not exceed 150 characters';
    }

    if (empty($data['price']) || !is_numeric($data['price']) || $data['price'] < 0) {
        $errors['price'] = 'Valid price is required';
    }

    if (!isset($data['stock']) || !is_numeric($data['stock']) || $data['stock'] < 0) {
        $errors['stock'] = 'Valid stock quantity is required';
    }

    if (!empty($data['category']) && strlen($data['category']) > 100) {
        $errors['category'] = 'Category must not exceed 100 characters';
    }

    return $errors;
}

