<?php
// notification_helper.php
// Complete notification system

require_once 'db.php';

class NotificationHelper {
    
    /**
     * Create a notification for a specific user
     */
    public static function create($conn, $user_id, $user_role, $type, $title, $message, $related_id = null) {
        $user_id = mysqli_real_escape_string($conn, $user_id);
        $user_role = mysqli_real_escape_string($conn, $user_role);
        $type = mysqli_real_escape_string($conn, $type);
        $title = mysqli_real_escape_string($conn, $title);
        $message = mysqli_real_escape_string($conn, $message);
        $related_id = $related_id ? mysqli_real_escape_string($conn, $related_id) : 'NULL';
        
        $query = "INSERT INTO notifications (user_id, user_role, type, title, message, related_id) 
                  VALUES ('$user_id', '$user_role', '$type', '$title', '$message', " . ($related_id ? "'$related_id'" : "NULL") . ")";
        
        return mysqli_query($conn, $query);
    }
    
    /**
     * Create notification for all admin users
     */
    public static function createForAdmins($conn, $type, $title, $message, $related_id = null) {
        // Get all admin users
        $admin_query = mysqli_query($conn, "SELECT id FROM users WHERE role = 'admin'");
        $success = true;
        
        while ($admin = mysqli_fetch_assoc($admin_query)) {
            if (!self::create($conn, $admin['id'], 'admin', $type, $title, $message, $related_id)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get all notifications for a user (newest first)
     */
    public static function getAll($conn, $user_id, $user_role, $limit = 50) {
        $user_id = mysqli_real_escape_string($conn, $user_id);
        $user_role = mysqli_real_escape_string($conn, $user_role);
        
        $query = "SELECT * FROM notifications 
                  WHERE user_id = '$user_id' AND user_role = '$user_role'
                  ORDER BY created_at DESC 
                  LIMIT $limit";
        
        return mysqli_query($conn, $query);
    }
    
    /**
     * Get recent notifications for header dropdown
     */
    public static function getRecent($conn, $user_id, $user_role, $limit = 5) {
        $user_id = mysqli_real_escape_string($conn, $user_id);
        $user_role = mysqli_real_escape_string($conn, $user_role);
        
        $query = "SELECT * FROM notifications 
                  WHERE user_id = '$user_id' AND user_role = '$user_role'
                  ORDER BY created_at DESC 
                  LIMIT $limit";
        
        return mysqli_query($conn, $query);
    }
    
    /**
     * Delete old notifications
     */
    public static function deleteOld($conn, $days = 30) {
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));
        $query = "DELETE FROM notifications WHERE created_at < '$date'";
        return mysqli_query($conn, $query);
    }
}
?>