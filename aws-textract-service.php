<?php
/**
 * Plugin Name: AWS_textract 
 * Author: Weii07_chen
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) {
    exit; // Prevent direct access to the file
}

add_action('wp_enqueue_scripts','textract_load_assets');
function textract_load_assets(){
    wp_enqueue_style(
        'bootstrap-css',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
        array(),
        '5.3.0',
        'all'
    );
    // 載入 jQuery（WordPress 內建 jQuery）
    wp_enqueue_script('jquery');

    // 載入 Bootstrap JS
    wp_enqueue_script(
        'bootstrap-js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
        array('jquery'),
        '5.3.0',
        true
    );
}
use Aws\Textract\TextractClient;
use Aws\S3\S3Client;
function upload_to_s3($file_data, $bucket, $key,$region) {
    $s3 = new S3Client([
        'version' => 'latest',
        'region' => $region,
    ]);

    try {
        $result = $s3->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'Body' => $file_data,
        ]);
    } catch (Exception $e) {
        return "Error uploading to S3: " . $e->getMessage();
    }

    return $result['ObjectURL']; // Return the file path on S3
}

function textract_upload_and_analyze($file) {
    $region = 'us-west-2';  // Default region
    $bucket = 'yourBucketName';
    $key = "uploads/" . basename($file['name']); // Path of the file on S3

    // Check uploaded file
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return "No file uploaded.";
    }

    // Get file type
    $fileType = mime_content_type($file['tmp_name']);

    // Load file
    $file_data = file_get_contents($file['tmp_name']);

    // Initialize Textract Client
    $textract = new TextractClient([
        'version' => 'latest',
        'region' => $region
    ]);

    try {
        if ($fileType == "application/pdf") {
            // Upload the file to S3
            $s3_url = upload_to_s3($file_data, $bucket, $key,$region);

            // Use startDocumentTextDetection to analyze
            $result = $textract->startDocumentTextDetection([
                'DocumentLocation' => [
                    'S3Object' => [
                        'Bucket' => $bucket,
                        'Name' => $key,
                    ],
                ],
            ]);

            $jobStatus = '';
            do {
                sleep(5); // Check every 5 seconds
                $statusResult = $textract->getDocumentTextDetection([
                    'JobId' => $result['JobId'],
                ]);
                $jobStatus = $statusResult['JobStatus'];
            } while ($jobStatus == 'IN_PROGRESS');

            if ($jobStatus == 'SUCCEEDED') { // SUCCEEDED, Update the extracted text
                $extractedText = '';
                foreach ($statusResult["Blocks"] as $block) {
                    if ($block["BlockType"] == "LINE") {
                        $extractedText .= $block["Text"] . " ";
                    }
                }
                update_option('textract_last_text', $extractedText);
                return "Success! Text extracted from PDF.";
            } else { // FAIL, Update the fail message
                update_option('textract_last_text', 'Failed with status' . $jobStatus);
                return "Textract job failed with status: " . $jobStatus;
            }

        } else {
            // Call Textract API to analyze
            $result = $textract->detectDocumentText([
                'Document' => [
                    'Bytes' => $file_data, // Sent file data
                ],
            ]);

            // Extract text
            $extractedText = "";
            foreach ($result["Blocks"] as $block) {
                if ($block["BlockType"] == "LINE") {
                    $extractedText .= $block["Text"] . " ";
                }
            }

            // Store in WordPress Options (For Shortcode to load)
            update_option('textract_last_text', $extractedText);

            return "Success! Text extracted.";
        }

    } catch (Exception $e) {
        return "Textract error: " . $e->getMessage();
    }
}


// Upload form
function textract_upload_form() {
    $action_url = esc_url(admin_url('admin-post.php')); // Sent the request to admin-post.php to handle the request
    return '<form action="' . $action_url . '" method="post" enctype="multipart/form-data" onsubmit="showLoading()">
                <input type="hidden" name="action" value="textract_upload">
                <input type="file" name="textract_file" required>
                <button type="submit">Upload and Analyze</button>
            </form>
            <p id="loadingMessage" style="display:none; color: red;">Processing... Please wait.</p>
            <script>
                function showLoading() {
                    document.getElementById("loadingMessage").style.display = "block";
                }
            </script>';  
}

// Upload form
function textract_handle_upload() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['textract_file'])) {
        $max_file_size = 5 * 1024 * 1024; // Set file size limit (5MB)
        // Check file size
        if ($_FILES['textract_file']['size'] > $max_file_size) {
            update_option('textract_last_text', 'Error: File size exceeds the 5MB limit.');
            wp_redirect($_SERVER["HTTP_REFERER"]);
            exit;
        }
        textract_upload_and_analyze($_FILES['textract_file']);
    }
    wp_redirect($_SERVER["HTTP_REFERER"]); // Redirect back to the original page
    exit;
}
add_action('admin_post_textract_upload', 'textract_handle_upload'); // use admin_post to handle the request

// Register the Shortcode
function textract_display_shortcode() {
    $extractedText = get_option('textract_last_text', 'No text extracted yet.');
    return "<div class='aws-textract-output' style='border: 1px solid #ccc; padding: 10px;'>
                <strong>Extracted Text:</strong>
                <p>{$extractedText}</p>
            </div>";
}

function clear_textract_results() {
    update_option('textract_last_text', 'No text extracted yet.'); // Clear the result
}

add_action('wp_head', 'clear_textract_results');

add_shortcode('textract_upload_form', 'textract_upload_form');
add_shortcode('textract_result', 'textract_display_shortcode');
?>
