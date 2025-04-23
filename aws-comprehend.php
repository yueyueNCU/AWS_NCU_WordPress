<?php
/**
 * Plugin Name: AWS_comprehend
 * Author: YUE YUE
 * Version: 1.0.0
 */
if( !defined('ABSPATH')){
    exit;
}
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

add_action('wp_enqueue_scripts','comprehend_load_assets');
function comprehend_load_assets(){
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
use WP_REST_Response;
use Aws\Comprehend\ComprehendClient;

class AWS_Comprehend{
    private $authentication_method; //驗證方法
    private $region ;               //所在區域
    private $accessKeyId;           //密鑰
    private $secretAccessKey;
    private $comprehendService;     //使用哪個comprehend功能
    public function __construct()
    {
        $this->authentication_method = 'api_key';
        $this->region        = 'us-west-2';  // Default region
        $this->accessKeyId   = 'YOUR_KEY';
        $this->secretAccessKey = 'YOUR_PRIVATE_KEY';

        add_shortcode('comprehend-form', array($this, 'text_comprehend_form'));

        add_action('wp_footer',array($this,'load_scripts'));

        add_action('rest_api_init',array($this, 'register_rest_api'));
    }
    public function text_comprehend_form() 
    {
        $output = '
            <div class="container mt-5">
                <!-- 表單標題 -->
                <h1>AWS Comprehend Form</h1>
                <p> Please choose the service</p>

                <!-- 表單開始，ID 為 aws-comprehend-form__form，稍後會用這個 ID 綁定 JavaScript 提交事件 -->
                <form id="aws-comprehend-form__form">
                    
                    <!-- 選擇 AWS Comprehend 分析服務的下拉選單 -->
                    <div class="form-group mb-2">
                        <select class="form-select form-control" name="comprehend_service">
                            <option value="Sentiment Analysis">Sentiment Analysis（情感分析）</option>
                            <option value="Syntax Analysis">Syntax Analysis（句法分析）</option>
                            <option value="Entity Analysis">Entity Analysis（實體辨識）</option>
                        </select>
                    </div>

                    <!-- 輸入欲分析文字的區域，使用 Bootstrap 的 form-floating 提示樣式 -->
                    <div class="form-group mb-2 form-floating">
                        <textarea class="form-control" name="text_to_be_analysed" placeholder="Leave a comment here" id="floatingTextarea" style="height: 200px"></textarea>
                        <label for="floatingTextarea">請輸入要分析文字</label>
                    </div>

                    <!-- 提交按鈕 -->
                    <div class="form-group">
                        <button class="btn btn-success btn-block" type="submit">Send</button>
                    </div>
                </form>

                <!-- 顯示分析結果或錯誤訊息的區塊，預設隱藏 -->
                <div id="result-message" class="alert" style="display:none;"></div>
            </div>
        ';

        // 回傳 HTML 內容
        return $output;
    }

    public function load_scripts()
{?>
    <script>
        var nonce= '<?php echo wp_create_nonce('wp_rest');?>';
        (function($){
            $(`#aws-comprehend-form__form`).off('submit').submit(function(event){
                event.preventDefault();
                var form = $(this).serialize();

                // 顯示 loading 樣式
                $('#aws-comprehend-form__form').find('button').prop('disabled', true); // 禁用提交按鈕
                $('#aws-comprehend-form__form').find('button').text('處理中...'); // 更改按鈕文字
                $('#result-message').removeClass('alert-success alert-danger').addClass('alert-info').text('處理中...').show(); // 顯示處理中訊息

                $.ajax({
                    method: 'post',
                    url: '<?php echo get_rest_url(null, 'comprehend-form/v1/send-api-to-aws');?>',
                    headers: { 'X-WP-Nonce': nonce },
                    data: form,
                    success: function(response) {
                        // 更新前端樣式與顯示回應結果
                        $('#aws-comprehend-form__form').find('button').prop('disabled', false); // 啟用提交按鈕
                        $('#aws-comprehend-form__form').find('button').text('送出'); // 恢復按鈕文字
                        $('#result-message').removeClass('alert-info alert-danger')
                            .addClass('alert-success')
                            .html(response.comprehend_output)
                            .show(); // 顯示成功訊息

                    },
                    error: function(xhr, status, error) {
                        // 更新錯誤顯示
                        $('#aws-comprehend-form__form').find('button').prop('disabled', false); // 啟用提交按鈕
                        $('#aws-comprehend-form__form').find('button').text('送出'); // 恢復按鈕文字
                        $('#result-message').removeClass('alert-info alert-success')
                            .addClass('alert-danger')
                            .text('❌ 發生錯誤：' + error)
                            .show(); // 顯示錯誤訊息
                        console.log('AJAX Error:', error);
                        console.log('Response:', xhr.responseText);
                    }
                });
            });
        })(jQuery);
    </script>
<?php }

    public function register_rest_api()
    {
        register_rest_route( 'comprehend-form/v1' , 'send-api-to-aws', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_comprehend_form'),
            'permission_callback' => function() {
                return true; // You might want to add actual permission check here
            }
        ));        
    }
    public function handle_comprehend_form($data){
        if (!wp_verify_nonce($data->get_header('x-wp-nonce'), 'wp_rest')) {
            return new WP_REST_Response(['message' => 'Message not sent'], 422);
        }
    
        $params = $data->get_params();
        $text = sanitize_text_field($params['text_to_be_analysed']);
        $service = sanitize_text_field($params['comprehend_service']);
        
        error_log('Received Text: ' . $text); 
    
        $result = $this->getRealAWS($text,$service); 


        return new WP_REST_Response([
            'message'            => 'Submission successful',
            'comprehend_service' => $service,
            'comprehend_output'  => $result
        ], 200);
    
    }
    public function getRealAWS($text,$service){
        $analysis_data = $this->analyzeText($text, $service);

            switch ($analysis_data['type']) {
                case 'sentiment':
                    return $this->generate_sentiment_html($analysis_data);
                case 'syntax':
                    return $this->generate_syntax_html($analysis_data);
                case 'entity':
                    return $this->generate_entity_html($analysis_data);
                default:
                    return '<p>Unknown analysis type</p>';
        }
    }
    public function analyzeText($text, $service) {
        try {
            $comprehend = $this->getAWSComprehendClient();

            if ($service === "Sentiment Analysis") {
                $result = $comprehend->detectSentiment([
                    'LanguageCode' => 'zh-TW',
                    'Text' => $text,
                ]);

                return [
                    'type' => 'sentiment',
                    'sentiment' => $result['Sentiment'],
                    'scores' => $result['SentimentScore']
                ];

            } elseif ($service === "Syntax Analysis") {
                $result = $comprehend->detectSyntax([
                    'LanguageCode' => 'en',
                    'Text' => $text,
                ]);

                return [
                    'type' => 'syntax',
                    'tokens' => $result['SyntaxTokens']
                ];

            } elseif ($service === "Entity Analysis") {
                $result = $comprehend->detectEntities([
                    'LanguageCode' => 'zh-TW',
                    'Text' => $text,
                ]);

                return [
                    'type' => 'entity',
                    'entities' => $result['Entities']
                ];

            } else {
                error_log("Unknown service type: $service");
                throw new Exception('Unknown service type');
            }

        } catch (Exception $e) {
            error_log('AWS Comprehend error: ' . $e->getMessage());
            throw $e; 
        }
    }
    private function getAWSComprehendClient() {
        $comprehendClient = null;
        if($this->authentication_method=='api_key'){
            if (empty($this->region)||empty($this->accessKeyId) || empty($this->secretAccessKey)) {
                throw new Exception('AWS credentials not configured.');
            }
            try{
                $comprehendClient =new ComprehendClient([
                    'region' => $this->region,
                    'version' => 'latest',
                    'credentials' => [
                        'key' => $this->accessKeyId,
                        'secret' => $this->secretAccessKey,
                    ],
                    'http' => [
                        'timeout' => 10,
                        'connect_timeout' => 5,
                    ],
                ]);
            }catch (\Exception $e) {
                // Handle SDK initialization error (e.g., log the error, throw an exception)
                error_log('Error initializing AWS SDK: ' . $e->getMessage());
                return null; // Or throw an exception if appropriate
            }
        }
        if($this->authentication_method=='iam_role'){
            try {
                $comprehendClient = new ComprehendClient([
                    'region' => $this->region,
                    'version' => 'latest',
                    'http' => [
                        'timeout' => 10,
                        'connect_timeout' => 5,
                    ],
                ]);
        
            } catch (\Exception $e) {
                // Handle SDK initialization error (e.g., log the error, throw an exception)
                error_log('Error initializing AWS SDK: ' . $e->getMessage());
                return null; // Or throw an exception if appropriate
            }
        }
        return $comprehendClient;
    }
    public function generate_sentiment_html($result) {
        $sentiment = esc_html($result['sentiment']);
        
        $html = '<div class="sentiment-analysis-result">';
        $html .= '<h4>情感分析結果</h4>';
        $html .= '<p>情感分析結果是: <strong>' . $sentiment . '</strong></p>';
        
        if (isset($result['scores'])) {
            $html .= '<table class="table table-striped table-bordered">';
            $html .= '<thead><tr><th>情感類型</th><th>可信度</th></tr></thead>';
            $html .= '<tbody>';
            
            foreach ($result['scores'] as $type => $score) {
                $html .= '<tr>';
                $html .= '<td>' . esc_html($type) . '</td>';
                $html .= '<td>' . round($score * 100, 1) . '%</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate HTML for syntax analysis results.
     */
    public function generate_syntax_html($result) {
        $tokens = $result['tokens'];
        
        $html = '<div class="syntax-analysis-result">';
        $html .= '<h4>語法分析結果</h4>';
        
        if (empty($tokens)) {
            $html .= '<p>未檢測到任何語法成分。</p>';
        } else {
            $html .= '<table class="table table-striped table-bordered">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th>詞語</th>';
            $html .= '<th>詞性</th>';
            $html .= '<th>可信度</th>';
            $html .= '<th>起始位置</th>';
            $html .= '<th>結束位置</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            foreach ($tokens as $token) {
                $text = esc_html($token['Text']);
                $partOfSpeech = esc_html($token['PartOfSpeech']['Tag']);
                $score = isset($token['PartOfSpeech']['Score']) ? round($token['PartOfSpeech']['Score'] * 100, 1) . '%' : 'N/A';
                $beginOffset = $token['BeginOffset'];
                $endOffset = $token['EndOffset'];
                
                $html .= '<tr>';
                $html .= '<td>' . $text . '</td>';
                $html .= '<td>' . $partOfSpeech . '</td>';
                $html .= '<td>' . $score . '</td>';
                $html .= '<td>' . $beginOffset . '</td>';
                $html .= '<td>' . $endOffset . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate HTML for entity analysis results.
     */
    public function generate_entity_html($result) {
        $entities = $result['entities'];
        
        $html = '<div class="entity-analysis-result">';
        $html .= '<h4>實體分析結果</h4>';
        
        if (empty($entities)) {
            $html .= '<p>未檢測到任何實體。</p>';
        } else {
            $html .= '<table class="table table-striped table-bordered">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th>實體</th>';
            $html .= '<th>類型</th>';
            $html .= '<th>可信度</th>';
            $html .= '<th>開始位置</th>';
            $html .= '<th>結束位置</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            foreach ($entities as $entity) {
                $text = esc_html($entity['Text']);
                $type = esc_html($entity['Type']);
                $score = round($entity['Score'] * 100, 1) . '%';
                $beginOffset = $entity['BeginOffset'];
                $endOffset = $entity['EndOffset'];
                
                $html .= '<tr>';
                $html .= '<td>' . $text . '</td>';
                $html .= '<td>' . $type . '</td>';
                $html .= '<td>' . $score . '</td>';
                $html .= '<td>' . $beginOffset . '</td>';
                $html .= '<td>' . $endOffset . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}
new AWS_Comprehend;