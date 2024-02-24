<?php
/*
Plugin Name: Contact Manager
Description: A WordPress plugin to manage contacts.
Version: 1.0
Author: Sergio Pinto
*/

// Create database tables during plugin activation
function contact_manager_activate() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Create people table if not exists
    $people_table_name = $wpdb->prefix . 'contact_manager_people';
    if ($wpdb->get_var("SHOW TABLES LIKE '$people_table_name'") != $people_table_name) {
        $people_sql = "CREATE TABLE $people_table_name (
            ID INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            deleted TINYINT NOT NULL DEFAULT 0,
            PRIMARY KEY (ID),
            UNIQUE (email)
        ) $charset_collate;";
        $wpdb->query($people_sql);
    }

    // Create contacts table if not exists
    $contacts_table_name = $wpdb->prefix . 'contact_manager_contacts';
    if ($wpdb->get_var("SHOW TABLES LIKE '$contacts_table_name'") != $contacts_table_name) {
        $contacts_sql = "CREATE TABLE $contacts_table_name (
            ID INT NOT NULL AUTO_INCREMENT,
            person_id INT NOT NULL,
            country_code VARCHAR(10) NOT NULL,
            number VARCHAR(9) NOT NULL,
            PRIMARY KEY (ID),
            UNIQUE (country_code, number),
            FOREIGN KEY (person_id) REFERENCES $people_table_name(ID) ON DELETE CASCADE
        ) $charset_collate;";
        $wpdb->query($contacts_sql);
    }
}
register_activation_hook(__FILE__, 'contact_manager_activate');

// Define plugin pages
function contact_manager_menu() {
    add_menu_page('People', 'People', 'manage_options', 'people-list', 'people_list_page');
    add_submenu_page('people-list', 'Add/Edit Person', 'Add/Edit Person', 'manage_options', 'add-edit-person', 'add_edit_person_page');
    add_submenu_page('people-list', 'Add/Edit Contact', 'Add/Edit Contact', 'manage_options', 'add-edit-contact', 'add_edit_contact_page');
}
add_action('admin_menu', 'contact_manager_menu');

// Function to display the people list page
function people_list_page() {
    global $wpdb;

    $people_table_name = $wpdb->prefix . 'contact_manager_people';

    // Check if a delete action is triggered
    if (isset($_GET['action']) && $_GET['action'] === 'delete') {
        // Verify nonce for security
        check_admin_referer('delete_person');

        // Get person ID from the URL
        $person_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        // Perform the soft delete
        $result = $wpdb->update(
            $people_table_name,
            array('deleted' => 1),
            array('ID' => $person_id)
        );

        // Check the result and set appropriate messages
        if ($result !== false) {
            $success_message = 'Person deleted successfully.';
            add_settings_error('contact_manager_person_success', '', $success_message, 'updated');
        } else {
            $error_message = 'Error occurred while deleting the person.';
            add_settings_error('contact_manager_person_error', '', $error_message, 'error');
        }
    }

    $people = $wpdb->get_results("SELECT * FROM $people_table_name WHERE deleted != 1", ARRAY_A);

    if ($wpdb->last_error) {
        echo "Error: " . $wpdb->last_error;
        return;
    }

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">People List</h1>
        <a href="<?php echo admin_url('admin.php?page=add-edit-person'); ?>" class="page-title-action">Add New Person</a>

        <?php settings_errors('contact_manager_person_error'); ?>
        <?php settings_errors('contact_manager_person_success'); ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($people as $person) : ?>
                    <tr>
                        <td><?php echo $person['ID']; ?></td>
                        <td><?php echo $person['name']; ?></td>
                        <td><?php echo $person['email']; ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=add-edit-person&id=' . $person['ID']); ?>">Edit</a>
                            <a href="<?php echo admin_url('admin.php?page=person-details&person_id=' . $person['ID']); ?>">Details</a>
                            <a href="<?php echo add_query_arg(array('action' => 'delete', 'id' => $person['ID']), wp_nonce_url(admin_url('admin.php?page=people-list'), 'delete_person')); ?>" class="delete" onclick="return confirm('Are you sure you want to delete this person?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Function to display the person details page
function person_details_page() {
    global $wpdb;

    $people_table_name = $wpdb->prefix . 'contact_manager_people';
    $contacts_table_name = $wpdb->prefix . 'contact_manager_contacts';

    // Get person ID from the URL
    $person_id = isset($_GET['person_id']) ? intval($_GET['person_id']) : 0;

    // Fetch person data
    $person = $wpdb->get_row($wpdb->prepare("SELECT * FROM $people_table_name WHERE ID = %d", $person_id), ARRAY_A);

    if (!$person) {
        echo "Person not found";
        return;
    }

    // Fetch contacts associated with the person
    $contacts = $wpdb->get_results($wpdb->prepare("SELECT * FROM $contacts_table_name WHERE person_id = %d", $person_id), ARRAY_A);
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Person Details - <?php echo esc_html($person['name']); ?></h1>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Country Code</th>
                    <th>Number</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contacts as $contact) : ?>
                    <tr>
                        <td><?php echo esc_html($contact['ID']); ?></td>
                        <td><?php echo esc_html($contact['country_code']); ?></td>
                        <td><?php echo esc_html($contact['number']); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=edit-contact&id=' . $contact['ID']); ?>">Edit</a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=person-details&person_id=' . $person_id . '&action=delete_contact&id=' . $contact['ID']), 'delete_contact'); ?>" class="delete" onclick="return confirm('Are you sure you want to delete this contact?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Function to display the edit contact page
function edit_contact_page() {
    global $wpdb;

    $contacts_table_name = $wpdb->prefix . 'contact_manager_contacts';

    // Get contact ID from the URL
    $contact_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // Fetch contact data
    $contact = $wpdb->get_row($wpdb->prepare("SELECT * FROM $contacts_table_name WHERE ID = %d", $contact_id), ARRAY_A);

    if (!$contact) {
        echo "Contact not found";
        return;
    }

    // Display edit contact form
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Edit Contact</h1>

        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th><label for="country_code">Country Code</label></th>
                    <td><input type="text" name="country_code" id="country_code" value="<?php echo esc_attr($contact['country_code']); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="number">Number</label></th>
                    <td><input type="text" name="number" id="number" value="<?php echo esc_attr($contact['number']); ?>" required></td>
                </tr>
            </table>

            <input type="hidden" name="contact_id" value="<?php echo esc_attr($contact_id); ?>">
            <?php submit_button('Update Contact'); ?>
        </form>
    </div>
    <?php
}

// Function to display the add/edit person page
function add_edit_person_page() {
    global $wpdb;

    $people_table_name = $wpdb->prefix . 'contact_manager_people';

    // Check if an ID is provided for editing an existing person
    $person_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $person = [];

    if ($person_id > 0) {
        // Fetch person data for editing
        $person = $wpdb->get_row($wpdb->prepare("SELECT * FROM $people_table_name WHERE ID = %d", $person_id), ARRAY_A);

        if (!$person) {
            echo "Person not found";
            return;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
        // Handle form submission
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);

        // Validate data
        if (strlen($name) < 5 || !is_email($email)) {
            // Handle validation errors
            $error_message = "Validation error: Name must be at least 5 characters, and email must be valid.";
            add_settings_error('contact_manager_person_error', '', $error_message, 'error');
        } else {
            // Insert or update person data
            $data = array(
                'name' => $name,
                'email' => $email,
            );

            if ($person_id > 0) {
                $result = $wpdb->update($people_table_name, $data, array('ID' => $person_id));
            } else {
                $result = $wpdb->insert($people_table_name, $data);
                $person_id = $wpdb->insert_id;
            }

            if ($result !== false) {
                // Success message
                $success_message = ($person_id > 0) ? 'Person updated successfully.' : 'Person added successfully.';
                add_settings_error('contact_manager_person_success', '', $success_message, 'updated');
            } else {
                // Error message
                $error_message = "Error occurred while saving the person.";
                add_settings_error('contact_manager_person_error', '', $error_message, 'error');
            }

            // Redirect to people list page or do something else
            wp_redirect(admin_url('admin.php?page=people-list'));
            exit();
        }
    }

    // Display error or success messages
    settings_errors('contact_manager_person_error');
    settings_errors('contact_manager_person_success');

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php echo ($person_id > 0) ? 'Edit Person' : 'Add New Person'; ?></h1>

        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th><label for="name">Name</label></th>
                    <td><input type="text" name="name" id="name" value="<?php echo esc_attr($person['name'] ?? ''); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="email">Email</label></th>
                    <td><input type="email" name="email" id="email" value="<?php echo esc_attr($person['email'] ?? ''); ?>" required></td>
                </tr>
            </table>

            <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
            <?php submit_button(($person_id > 0) ? 'Update Person' : 'Add Person'); ?>
        </form>
    </div>
    <?php
}

// Function to display the add/edit contact page
function add_edit_contact_page() {
    global $wpdb;

    $people_table_name = $wpdb->prefix . 'contact_manager_people';
    $contacts_table_name = $wpdb->prefix . 'contact_manager_contacts';

    // Check if a person ID is provided for editing an existing contact
    $contact_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $contact = [];

    if ($contact_id > 0) {
        // Fetch contact data for editing
        $contact = $wpdb->get_row($wpdb->prepare("SELECT * FROM $contacts_table_name WHERE ID = %d", $contact_id), ARRAY_A);

        if (!$contact) {
            echo "Contact not found";
            return;
        }
    }

    // Handle form submission
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $person_id = intval($_POST['person_id']);
    $country_code = sanitize_text_field($_POST['country_code']);
    $number = sanitize_text_field($_POST['number']);

    // Validate data
    if (!is_numeric($person_id) || strlen($number) !== 9 || !ctype_digit($number)) {
        // Handle validation errors
        echo "Validation error: Invalid input.";
        return;
    }

        // Insert or update contact data
        $data = array(
            'person_id' => $person_id,
            'country_code' => $country_code,
            'number' => $number,
        );

        if ($contact_id > 0) {
            $result = $wpdb->update($contacts_table_name, $data, array('ID' => $contact_id));
        } else {
            $result = $wpdb->insert($contacts_table_name, $data);
            $contact_id = $wpdb->insert_id;
        }

        if ($result === false) {
            echo "Error occurred while saving the contact.";
            return;
        }

        // Redirect to person details page or do something else
        wp_redirect(admin_url("admin.php?page=person-details&person_id=$person_id"));
        exit();
    }

// Fetch calling codes from the API
$calling_codes = [];

// Make an HTTP request to the API
$response = wp_remote_get('https://restcountries.com/v2/all');

// Check if the request was successful
if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
    // Parse the JSON response
    $body = wp_remote_retrieve_body($response);
    $countries = json_decode($body, true);

    // Extract calling codes
    foreach ($countries as $country) {
        $calling_code = isset($country['callingCodes'][0]) ? $country['callingCodes'][0] : '';
        $name = isset($country['name']) ? $country['name'] : '';

        // Add to the calling_codes array
        if ($calling_code && $name) {
            $calling_codes[] = array(
                'calling_code' => $calling_code,
                'name' => $name,
            );
        }
    }
} else {
    // Handle error, e.g., log or display a message
    echo 'Failed to fetch calling codes from the API.';
}

// Display add/edit contact form
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo ($contact_id > 0) ? 'Edit Contact' : 'Add New Contact'; ?></h1>

    <form method="post" action="">
        <table class="form-table">
            <tr>
                <th><label for="person_id">Person</label></th>
                <td>
                    <?php
                    $people = $wpdb->get_results("SELECT ID, name FROM $people_table_name", ARRAY_A);
                    ?>
                    <select name="person_id" id="person_id" required>
                        <?php foreach ($people as $person) : ?>
                            <option value="<?php echo esc_attr($person['ID']); ?>" <?php selected($person['ID'], $contact['person_id'] ?? 0); ?>><?php echo esc_html($person['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="country_code">Country Code</label></th>
                <td>
                    <select name="country_code" id="country_code" required>
                        <?php foreach ($calling_codes as $calling_code) : ?>
                            <option value="<?php echo esc_attr($calling_code['calling_code']); ?>" <?php selected($calling_code['calling_code'], $contact['country_code'] ?? ''); ?>>
                                <?php echo esc_html($calling_code['name']) . ' (' . esc_html($calling_code['calling_code']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="number">Number</label></th>
                <td><input type="text" name="number" id="number" value="<?php echo esc_attr($contact['number'] ?? ''); ?>" required></td>
            </tr>
        </table>

        <input type="hidden" name="contact_id" value="<?php echo esc_attr($contact_id); ?>">
        <?php submit_button(($contact_id > 0) ? 'Update Contact' : 'Add Contact'); ?>
    </form>
</div>
<?php
}

?>
