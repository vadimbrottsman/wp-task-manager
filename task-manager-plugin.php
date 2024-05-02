<?php
/*
Plugin Name: Task Manager Plugin
Description: Личный таск-менеджер
Version:  4.0
*/

function task_manager_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tasks';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        description text NOT NULL,
        date_time datetime NOT NULL,
        completed tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook( __FILE__, 'task_manager_install' );

add_action('admin_menu', 'task_manager_menu');

function task_manager_menu(){
    add_menu_page(
        'Task Manager', 
        'Task Manager', 
        'manage_options', 
        'task-manager', 
        'task_manager_page'
    );
}

add_action('wp_ajax_add_task_action', 'add_task_function');
function task_manager_page(){
    ?>
    <div class="wrap">
        <h2>Личный таск менеджер</h2>
        <button id="add-task-button" class="btn btn-primary mb-2" data-toggle="modal" data-target="#addTaskModal">Добавить задачу</button>

        <div class="modal fade" id="addTaskModal" tabindex="-1" role="dialog" aria-labelledby="addTaskModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addTaskModalLabel">Добавить задачу</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="add-task-form" action="<?php echo admin_url('admin-ajax.php'); ?>">
                            <div class="form-group">  
                                <label for="task-name">Наименование задачи:</label>
                                <input type="text" class="form-control" id="task-name" name="task_name">
                            </div>
                            <div class="form-group">  
                                <label for="task-description">Описание:</label>
                                <textarea class="form-control" id="task-description" name="task_description"></textarea>
                            </div>
                            <div class="form-group">  
                                <label for="task-datetime">Дата и время:</label>
                                <input type="datetime-local" class="form-control" id="task-datetime" name="task_datetime">
                            </div>
                            <?php wp_nonce_field( 'add_task_nonce', 'add_task_nonce' ); ?>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Закрыть</button>
                        <button type="button" id="submit-task" class="btn btn-primary">Добавить задачу</button>
                    </div>
                </div>
            </div>
        </div>
        <div id="task-list">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Наименование</th>
                        <th scope="col">Описание</th>
                        <th scope="col">Дата и время</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Получение данных задач из базы данных
                    $tasks = get_tasks_from_database(); 
                    foreach($tasks as $task){
                        $class = ($task->completed == 1) ? 'table-success' : '';
                        echo '<tr class="' . $class . '">';
                        echo '<th scope="row">' . $task->id . '</th>';
                        echo '<td>' . $task->name . '</td>';
                        echo '<td>' . $task->description . '</td>';
                        echo '<td>' . $task->date_time . '</td>';
                        echo '<td><button class="btn btn-primary edit-task" data-taskid="' . $task->id . '" data-toggle="modal" data-target="#editTaskModal">Редактировать</button></td>';
                        
                        echo '<td><button class="btn btn-success complete-task" data-taskid="' . $task->id . '">Выполненно</button></td>';
                        echo '<td><button class="btn btn-warning mark-as-pending" data-taskid="' . $task->id . '">Вернуть</button></td>';
                        echo '<td><button class="btn btn-danger delete-task" data-taskid="' . $task->id . '">Удалить</button></td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="modal fade" id="editTaskModal" tabindex="-1" role="dialog" aria-labelledby="editTaskModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTaskModalLabel">Редактировать задачу</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="edit-task-form">
                              <input type="hidden" id="edit-task-id" name="task_id">
                                <div class="form-group">
                                    <label for="edit-task-name">Наименование задачи:</label>
                                    <input type="text" class="form-control" id="edit-task-name" name="task_name">
                                </div>
                                <div class="form-group">
                                    <label for="edit-task-description">Описание:</label>
                                    <textarea class="form-control" id="edit-task-description" name="task_description"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="edit-task-datetime">Дата и время:</label>
                                    <input type="datetime-local" class="form-control" id="edit-task-datetime" name="task_datetime">
                                </div>
                            <?php wp_nonce_field( 'edit_task_nonce', 'edit_task_nonce' ); ?>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Закрыть</button>
                        <button type="button" id="save-task" class="btn btn-primary">Сохранить задачу</button>
                    </div>
                </div>
            </div>
        </div>
    </div>  
  <script> 
    jQuery(document).ready(function($){
        
        $('#submit-task').click(function(){
            var data = {
                action: 'add_task_action',
                task_name: $('#task-name').val(),
                task_description: $('#task-description').val(),
                task_datetime: $('#task-datetime').val(),
                security: '<?php echo wp_create_nonce('add_task_nonce'); ?>'
            };
            $.post(ajaxurl, data, function(response) { 
                console.log(response); 
                location.reload();
            });
            $('#addTaskModal').modal('hide');
        });
   
        $('.edit-task').click(function(){
            var task_id = $(this).data('taskid');
            $.post(ajaxurl, { action: 'get_task_data', task_id: task_id, security: '<?php echo wp_create_nonce('edit_task_nonce'); ?>' }, function(response) {
                var data = $.parseJSON(response);
                if(data.error) {
                    console.log(data.error);
                } else {
                    $('#edit-task-id').val(data.id);
                    $('#edit-task-name').val(data.name);
                    $('#edit-task-description').val(data.description);
                    $('#edit-task-datetime').val(data.date_time);
                    $('#editTaskModal').modal('show');
                }
            });
        });
    
        $('#save-task').click(function(){
            var data = {
                action: 'edit_task_action',
                task_id: $('#edit-task-id').val(),
                task_name: $('#edit-task-name').val(),
                task_description: $('#edit-task-description').val(),
                task_datetime: $('#edit-task-datetime').val(),
                security: $('#edit_task_nonce').val()
            };
            $.post(ajaxurl, data, function(response) { 
                console.log(response); 
                location.reload();
            });
            $('#editTaskModal').modal('hide');
        });

    
   
      function updateTasksTable(){
            $.get(ajaxurl, { action: 'get_updated_tasks_table' }, function(response){
                $('#task-list').html(response);
            });
        }
        
        $('.complete-task').on('click', function(){
            var button = $(this); // Сохраняем ссылку на кнопку
            var taskId = button.data('taskid');
            $.post(ajaxurl, { action: 'complete_task', id: taskId }, function(response){
                alert(response);
                button.closest('tr').addClass('table-success');
            });
        });
        
        $('.mark-as-pending').on('click', function(){
            var taskId = $(this).data('taskid');
            $.post(ajaxurl, { action: 'mark_as_pending', id: taskId }, function(response){
                alert(response);
                location.reload();
            });
        });
        
        $('.delete-task').on('click', function(){
            var button = $(this); // Сохраняем ссылку на кнопку
            var taskId = button.data('taskid');
            $.post(ajaxurl, { action: 'delete_task', id: taskId }, function(response){
                alert(response);
                button.closest('tr').remove();
            });
        });
});
    </script>
    <?php
}

add_action('wp_ajax_get_updated_tasks_table', 'get_updated_tasks_table_callback');
function get_updated_tasks_table_callback(){
    $tasks = get_tasks_from_database(); 
    ob_start();
    foreach($tasks as $task){
        // Вывод строк таблицы задач
    }
    $html = ob_get_clean();
    echo $html;
    wp_die();
}

add_action('wp_ajax_complete_task', 'complete_task_callback');
function complete_task_callback(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tasks';
    $task_id = intval( $_POST['id'] );
    $wpdb->update( $table_name, array( 'completed' => 1 ), array( 'id' => $task_id ) );
    
    echo "Задача помечена выполненной";
    wp_die();
}

add_action('wp_ajax_delete_task', 'delete_task_callback');
function delete_task_callback(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tasks';
    $task_id = intval( $_POST['id'] );
    $wpdb->delete( $table_name, array( 'id' => $task_id ) );
    
    echo "Задача удалена";
    wp_die();
}

add_action('wp_ajax_mark_as_pending', 'mark_as_pending_callback');
function mark_as_pending_callback(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tasks';
    $task_id = intval( $_POST['id'] );
    $wpdb->update( $table_name, array( 'completed' => 0 ), array( 'id' => $task_id ) );
    
    echo "Задача возвращена в работу";
    wp_die();
}



add_action('wp_ajax_get_task_data', 'get_task_data');

function get_task_data() {
    check_ajax_referer( 'edit_task_nonce', 'security' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'tasks';
    $task_id = $_POST['task_id'];

    $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $task_id), OBJECT);
    
    if($task) {
        echo json_encode($task);
    } else {
        echo 'Ошибка: не найдена задача';
    }

    wp_die();
}

function get_tasks_from_database(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tasks';
    
    $tasks = $wpdb->get_results("SELECT * FROM $table_name ORDER BY date_time DESC");

    return $tasks;
}

function add_task_function(){
    check_ajax_referer( 'add_task_nonce', 'security' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'tasks';

    $task_name = sanitize_text_field($_POST['task_name']);
    $task_description = sanitize_text_field($_POST['task_description']);
    $task_datetime = $_POST['task_datetime']; 

    $wpdb->insert( 
        $table_name, 
        array( 
            'name' => $task_name, 
            'description' => $task_description, 
            'date_time' => $task_datetime
        ) 
    );

    echo 'Задача добавленна успешно';

    wp_die();
}

add_action('wp_ajax_edit_task_action', 'edit_task_action');

function edit_task_action() {
    check_ajax_referer( 'edit_task_nonce', 'security' );

    $task_id = $_POST['task_id'];
    $task_name = sanitize_text_field($_POST['task_name']);
    $task_description = sanitize_text_field($_POST['task_description']);
    $task_datetime = $_POST['task_datetime'];

    global $wpdb;
    $table_name = $wpdb->prefix . 'tasks';
    $wpdb->update( 
        $table_name, 
        array( 
            'name' => $task_name, 
            'description' => $task_description, 
            'date_time' => $task_datetime 
        ), 
        array( 'id' => $task_id ) 
    );

    wp_die();
}