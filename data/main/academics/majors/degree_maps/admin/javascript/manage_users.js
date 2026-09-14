$(document).ready(function() {
    function loadUsers() {
        $.ajax({
            url: 'ajax/get_users.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let html = '<table border="1"><thead><tr><th>Name</th><th>Permission</th><th>College</th><th>Department</th><th>Actions</th></tr></thead><tbody>';
                    response.users.forEach(function(user) {
                        html += `<tr>
                                    <td><a href="mailto:${user.email}">${user.first_name} ${user.last_name}</a></td>
                                    <td>${user.permission_level}</td>
                                    <td>${user.college}</td>
                                    <td>${user.department}</td>
                                    <td>
                                        <button class="edit-user-btn" data-user-id="${user.id}">Edit</button>
                                        <button class="delete-user-btn" data-user-id="${user.id}">Delete</button>
                                    </td>
                                </tr>`;
                    });
                    html += '</tbody></table>';
                    $('#user-list').html(html);
                } else {
                    $('#user-list').html(`<p>Error: ${response.message}</p>`);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching users:', error);
                $('#user-list').html('<p>An error occurred while fetching users.</p>');
            }
        });
    }

    loadUsers();

    $('#cancel-user-btn').on('click', function() {
        $('#user-form-modal').hide();
    });

    $(document).on('click', '.delete-user-btn', function() {
        if (!confirm('Are you sure you want to delete this user?')) {
            return;
        }
        const userId = $(this).data('user-id');
        $.ajax({
            url: 'ajax/delete_user.php',
            method: 'POST',
            data: { user_id: userId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    loadUsers();
                } else {
                    alert(`Error: ${response.message}`);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error deleting user:', error);
                alert('An error occurred while deleting the user.');
            }
        });
    });

    $(document).on('click', '.edit-user-btn', function() {
        const userId = $(this).data('user-id');
        $.ajax({
            url: 'ajax/get_user_form.php',
            method: 'POST',
            data: { user_id: userId },
            dataType: 'html',
            success: function(html) {
                $('#user-form-modal').html(html).dialog({
                    modal: true,
                    title: 'Edit User',
                    width: 600,
                    close: function() {
                        $(this).dialog('destroy').hide();
                    }
                });
            },
            error: function(xhr, status, error) {
                console.error('Error loading user form:', error);
                alert('An error occurred while loading the user form.');
            }
        });
    });

    $('#add-user-btn').on('click', function() {
        $.ajax({
            url: 'ajax/get_user_form.php',
            method: 'POST',
            dataType: 'html',
            success: function(html) {
                $('#user-form-modal').html(html).dialog({
                    modal: true,
                    title: 'Add New User',
                    width: 600,
                    close: function() {
                        $(this).dialog('destroy').hide();
                    }
                });
            },
            error: function(xhr, status, error) {
                console.error('Error loading user form:', error);
                alert('An error occurred while loading the user form.');
            }
        });
    });

    $(document).on('submit', '#user-form', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax/save_user.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    loadUsers();
                    $('#user-form-modal').dialog('close');
                } else {
                    alert(`Error: ${response.message}`);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error saving user:', error);
                alert('An error occurred while saving the user.');
            }
        });
    });
});