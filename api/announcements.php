<?php
/**
 * Announcements API
 * CIG Admin Dashboard - Announcement Management
 */

require_once '../db/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

class AnnouncementAPI {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Get Latest Active Announcement
     */
    public function getLatestAnnouncement() {
        $query = "SELECT a.*, u.full_name as created_by_name 
                  FROM announcements a 
                  LEFT JOIN users u ON a.created_by = u.user_id 
                  WHERE a.is_active = 1 
                  ORDER BY a.created_at DESC 
                  LIMIT 1";
        return $this->db->fetchRow($query);
    }

    /**
     * Get All Announcements
     */
    public function getAllAnnouncements($active_only = true) {
        $query = "SELECT a.*, u.full_name as created_by_name, 
                         u2.full_name as updated_by_name
                  FROM announcements a 
                  LEFT JOIN users u ON a.created_by = u.user_id 
                  LEFT JOIN users u2 ON a.updated_by = u2.user_id";
        
        if ($active_only) {
            $query .= " WHERE a.is_active = 1";
        }
        
        $query .= " ORDER BY a.created_at DESC";
        return $this->db->fetchAll($query);
    }

    /**
     * Get Announcement by ID
     */
    public function getAnnouncement($announcement_id) {
        $query = "SELECT a.*, u.full_name as created_by_name, 
                         u2.full_name as updated_by_name
                  FROM announcements a 
                  LEFT JOIN users u ON a.created_by = u.user_id 
                  LEFT JOIN users u2 ON a.updated_by = u2.user_id 
                  WHERE a.announcement_id = ?";
        return $this->db->fetchRow($query, [$announcement_id]);
    }

    /**
     * Create New Announcement
     */
    public function createAnnouncement($title, $content, $created_by, $category = 'General', $priority = 'normal') {
        $query = "INSERT INTO announcements (title, content, category, priority, created_by, is_active, created_at) 
                  VALUES (?, ?, ?, ?, ?, 1, NOW())";
        $result = $this->db->execute($query, [$title, $content, $category, $priority, $created_by]);
        
        if ($result) {
            return $this->db->getLastInsertId();
        }
        return false;
    }

    /**
     * Update Announcement
     */
    public function updateAnnouncement($announcement_id, $title, $content, $updated_by, $category = null, $priority = null, $is_active = null) {
        $query = "UPDATE announcements SET title = ?, content = ?, updated_by = ?, updated_at = NOW()";
        $params = [$title, $content, $updated_by];
        
        if ($category !== null) {
            $query .= ", category = ?";
            $params[] = $category;
        }
        
        if ($priority !== null) {
            $query .= ", priority = ?";
            $params[] = $priority;
        }
        
        if ($is_active !== null) {
            $query .= ", is_active = ?";
            $params[] = $is_active;
        }
        
        $query .= " WHERE announcement_id = ?";
        $params[] = $announcement_id;
        
        return $this->db->execute($query, $params);
    }

    /**
     * Delete Announcement
     */
    public function deleteAnnouncement($announcement_id) {
        $query = "DELETE FROM announcements WHERE announcement_id = ?";
        return $this->db->execute($query, [$announcement_id]);
    }

    /**
     * Toggle Announcement Active Status
     */
    public function toggleAnnouncement($announcement_id, $updated_by) {
        $query = "UPDATE announcements 
                  SET is_active = NOT is_active, updated_by = ?, updated_at = NOW()
                  WHERE announcement_id = ?";
        return $this->db->execute($query, [$updated_by, $announcement_id]);
    }
}

// Handle API Requests
try {
    $api = new AnnouncementAPI();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? null;

    switch ($method) {
        case 'GET':
            if ($action === 'latest') {
                $announcement = $api->getLatestAnnouncement();
                echo json_encode([
                    'success' => true,
                    'data' => $announcement
                ]);
            } elseif ($action === 'all') {
                $active_only = isset($_GET['active']) ? (bool)$_GET['active'] : true;
                $announcements = $api->getAllAnnouncements($active_only);
                echo json_encode([
                    'success' => true,
                    'data' => $announcements
                ]);
            } elseif ($action === 'get' && isset($_GET['id'])) {
                $announcement = $api->getAnnouncement($_GET['id']);
                echo json_encode([
                    'success' => (bool)$announcement,
                    'data' => $announcement
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid action'
                ]);
            }
            break;

        case 'POST':
            if ($action === 'create') {
                $title = $_POST['title'] ?? '';
                $content = $_POST['content'] ?? '';
                $created_by = intval($_POST['created_by'] ?? $_POST['user_id'] ?? 1);
                $created_by = $created_by > 0 ? $created_by : 1;
                $category = $_POST['category'] ?? 'General';
                $priority = $_POST['priority'] ?? 'normal';

                if (empty($title) || empty($content)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Missing required fields: title and content are required'
                    ]);
                    break;
                }

                $announcement_id = $api->createAnnouncement($title, $content, $created_by, $category, $priority);
                echo json_encode([
                    'success' => (bool)$announcement_id,
                    'announcement_id' => $announcement_id,
                    'message' => $announcement_id ? 'Announcement created successfully' : 'Failed to create announcement'
                ]);
            } elseif ($action === 'update') {
                // Handle update via POST
                $announcement_id = $_POST['announcement_id'] ?? null;
                $title = $_POST['title'] ?? '';
                $content = $_POST['content'] ?? '';
                // Fallback: updated_by can come as created_by if JS sends wrong key
                $updated_by = intval($_POST['updated_by'] ?? $_POST['created_by'] ?? 1);
                $updated_by = $updated_by > 0 ? $updated_by : 1;
                $category = $_POST['category'] ?? null;
                $priority = $_POST['priority'] ?? null;
                $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : null;

                if (!$announcement_id || empty($title) || empty($content)) {
                    $missing = [];
                    if (!$announcement_id) $missing[] = 'announcement_id';
                    if (empty($title)) $missing[] = 'title';
                    if (empty($content)) $missing[] = 'content';
                    
                    echo json_encode([
                        'success' => false,
                        'message' => 'Missing required fields: ' . implode(', ', $missing),
                        'debug' => ['missing' => $missing, 'received' => ['announcement_id' => $announcement_id]]
                    ]);
                    break;
                }

                $result = $api->updateAnnouncement($announcement_id, $title, $content, $updated_by, $category, $priority, $is_active);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Announcement updated successfully' : 'Failed to update announcement'
                ]);
            } elseif ($action === 'delete') {
                // Handle delete via POST
                $announcement_id = $_POST['announcement_id'] ?? null;

                if (!$announcement_id) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Missing required field: announcement_id'
                    ]);
                    break;
                }

                $result = $api->deleteAnnouncement($announcement_id);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Announcement deleted successfully' : 'Failed to delete announcement'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid action'
                ]);
            }
            break;

        case 'PUT':
            parse_str(file_get_contents("php://input"), $_PUT);
            
            if ($action === 'update') {
                $announcement_id = $_PUT['announcement_id'] ?? null;
                $title = $_PUT['title'] ?? '';
                $content = $_PUT['content'] ?? '';
                $updated_by = $_PUT['updated_by'] ?? null;
                $category = $_PUT['category'] ?? null;
                $priority = $_PUT['priority'] ?? null;
                $is_active = isset($_PUT['is_active']) ? (bool)$_PUT['is_active'] : null;

                if (!$announcement_id || empty($title) || empty($content) || !$updated_by) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Missing required fields'
                    ]);
                    break;
                }

                $result = $api->updateAnnouncement($announcement_id, $title, $content, $updated_by, $category, $priority, $is_active);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Announcement updated successfully' : 'Failed to update announcement'
                ]);
            } elseif ($action === 'toggle') {
                $announcement_id = $_PUT['announcement_id'] ?? null;
                $updated_by = $_PUT['updated_by'] ?? null;

                if (!$announcement_id || !$updated_by) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Missing required fields'
                    ]);
                    break;
                }

                $result = $api->toggleAnnouncement($announcement_id, $updated_by);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Announcement toggled successfully' : 'Failed to toggle announcement'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid action'
                ]);
            }
            break;

        case 'DELETE':
            parse_str(file_get_contents("php://input"), $_DELETE);
            
            if ($action === 'delete' && isset($_DELETE['announcement_id'])) {
                $result = $api->deleteAnnouncement($_DELETE['announcement_id']);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Announcement deleted successfully' : 'Failed to delete announcement'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid action'
                ]);
            }
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>