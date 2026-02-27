<?php
/**
 * Submissions Operations API
 * CIG Admin Dashboard - Submission Management
 */

require_once '../db/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

class SubmissionAPI {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Get Submission by ID
     */
    public function getSubmission($submission_id) {
        $query = "SELECT s.*, u.full_name, o.org_name FROM submissions s 
                  LEFT JOIN users u ON s.user_id = u.user_id 
                  LEFT JOIN organizations o ON s.org_id = o.org_id 
                  WHERE s.submission_id = ?";
        return $this->db->fetchRow($query, [$submission_id]);
    }

    /**
     * Get All Submissions
     */
    public function getAllSubmissions($status = null) {
        $query = "SELECT s.*, u.full_name, o.org_name FROM submissions s 
                  LEFT JOIN users u ON s.user_id = u.user_id 
                  LEFT JOIN organizations o ON s.org_id = o.org_id";
        
        if ($status) {
            $query .= " WHERE s.status = ?";
            return $this->db->fetchAll($query, [$status]);
        }
        
        $query .= " ORDER BY s.submitted_at DESC";
        return $this->db->fetchAll($query);
    }

    /**
     * Get Submissions by User
     */
    public function getUserSubmissions($user_id) {
        $query = "SELECT s.*, o.org_name FROM submissions s 
                  LEFT JOIN organizations o ON s.org_id = o.org_id 
                  WHERE s.user_id = ? ORDER BY s.submitted_at DESC";
        return $this->db->fetchAll($query, [$user_id]);
    }

    /**
     * Get Submissions by Organization
     */
    public function getOrgSubmissions($org_id) {
        $query = "SELECT s.*, u.full_name FROM submissions s 
                  LEFT JOIN users u ON s.user_id = u.user_id 
                  WHERE s.org_id = ? ORDER BY s.submitted_at DESC";
        return $this->db->fetchAll($query, [$org_id]);
    }

    /**
     * Create Submission
     */
    public function createSubmission($data) {
        return $this->db->insert('submissions', $data);
    }

    /**
     * Update Submission
     */
    public function updateSubmission($submission_id, $data) {
        unset($data['submission_id']);
        return $this->db->update('submissions', $data, 'submission_id = ?', [$submission_id]);
    }

    /**
     * Update Submission Status
     */
    public function updateStatus($submission_id, $status) {
        return $this->db->update('submissions', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], 'submission_id = ?', [$submission_id]);
    }

    /**
     * Approve Submission
     */
    public function approveSubmission($submission_id) {
        return $this->updateStatus($submission_id, 'approved');
    }

    /**
     * Reject Submission
     */
    public function rejectSubmission($submission_id, $reason = '') {
        $data = ['status' => 'rejected', 'updated_at' => date('Y-m-d H:i:s')];
        if ($reason) {
            $data['rejection_reason'] = $reason;
        }
        return $this->db->update('submissions', $data, 'submission_id = ?', [$submission_id]);
    }

    /**
     * Delete Submission
     */
    public function deleteSubmission($submission_id) {
        return $this->db->delete('submissions', 'submission_id = ?', [$submission_id]);
    }

    /**
     * Get Submission Statistics
     */
    public function getStatistics() {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_review
                  FROM submissions";
        return $this->db->fetchRow($query);
    }
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$api = new SubmissionAPI();

try {
    if ($method === 'GET') {
        if ($action === 'get' && isset($_GET['id'])) {
            echo json_encode($api->getSubmission($_GET['id']));
        } elseif ($action === 'download' && isset($_GET['submission_id'])) {
            // Download submission as JSON
            $submission_id = $_GET['submission_id'];
            $submission = $api->getSubmission($submission_id);
            if ($submission) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="submission_' . $submission_id . '.json"');
                echo json_encode($submission, JSON_PRETTY_PRINT);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Submission not found']);
            }
        } elseif ($action === 'getAll') {
            $status = $_GET['status'] ?? null;
            echo json_encode($api->getAllSubmissions($status));
        } elseif ($action === 'byUser' && isset($_GET['user_id'])) {
            echo json_encode($api->getUserSubmissions($_GET['user_id']));
        } elseif ($action === 'byOrg' && isset($_GET['org_id'])) {
            echo json_encode($api->getOrgSubmissions($_GET['org_id']));
        } elseif ($action === 'statistics') {
            echo json_encode($api->getStatistics());
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } elseif ($method === 'POST') {
        // Handle form-encoded POST data
        if (!empty($_POST)) {
            $action = $_POST['action'] ?? null;
            $submission_id = $_POST['submission_id'] ?? null;

            if ($action === 'approve' && $submission_id) {
                try {
                    $api->approveSubmission($submission_id);
                    echo json_encode(['success' => true, 'message' => 'Submission approved successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            } elseif ($action === 'reject' && $submission_id) {
                $reason = $_POST['reason'] ?? '';
                try {
                    $api->rejectSubmission($submission_id, $reason);
                    echo json_encode(['success' => true, 'message' => 'Submission rejected successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            } elseif ($action === 'download' && $submission_id) {
                // For download, send the submission data or file
                try {
                    $submission = $api->getSubmission($submission_id);
                    if ($submission) {
                        header('Content-Type: application/json');
                        echo json_encode($submission);
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'Submission not found']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['error' => $e->getMessage()]);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
        } else {
            // Handle JSON POST data
            $data = json_decode(file_get_contents('php://input'), true);
            if ($action === 'create') {
                $id = $api->createSubmission($data);
                echo json_encode(['success' => true, 'id' => $id]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
        }
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($action === 'update' && isset($_GET['id'])) {
            $rowCount = $api->updateSubmission($_GET['id'], $data);
            echo json_encode(['success' => true, 'updated' => $rowCount]);
        } elseif ($action === 'updateStatus' && isset($_GET['id'])) {
            $rowCount = $api->updateStatus($_GET['id'], $data['status']);
            echo json_encode(['success' => true, 'updated' => $rowCount]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } elseif ($method === 'DELETE') {
        if ($action === 'delete' && isset($_GET['id'])) {
            $rowCount = $api->deleteSubmission($_GET['id']);
            echo json_encode(['success' => true, 'deleted' => $rowCount]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
