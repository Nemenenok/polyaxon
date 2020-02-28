<?php
/**
 * Created by PhpStorm.
 * User: Victor Nemenenok
 * Date: 18.12.2019
 * Time: 11:21
 */

namespace Training\Clients;

use Carbon\Carbon;
use models\Settings;
use MongoDB\BSON\UTCDateTime;
use yii\httpclient\Client;

class Polyaxon extends Clients {

    // Версия API
    const VERSION = 'v1';

    // Параметры авторизации
    private $username;
    private $password;
    private $token;
    private $token_type = 'token';

    // Адрес хоста
    private $host;

    // Проект в системе Polyaxon
    private $project;

    // Заголовки запроса
    protected $headers = [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];

    // Статусы
    private $statuses = [
        'created' => 'Experiment creation',
        'building' => 'Container assembly',
        'scheduled' => 'Ready to run',
        'starting' => 'Starting',
        'running' => 'Running',
        'resuming' => 'Resume starting',
        'unknown' => 'Unknown',
        'succeeded' => 'Succeeded',
        'stopped' => 'Stopped',
        'failed' => 'Failed',
        'warning' => 'Waiting for a resource to run',
        'unschedulable' => 'Waiting for a resource to run',
        'upstream_failed' => 'Error of previous experiment',
        'skipped' => 'Experiment skipped',
    ];

    /**
     * Конструктор.
     * Загрузка настроек, авторизация в сервисе
     */
    public function __construct() {
        parent::__construct();

        $setting_name = strtolower((new \ReflectionClass($this))->getShortName());

        // Загрузка настроек
        if($settings = Settings::getByName($setting_name,true)){

            // Адрес хоста
            $this->host = $settings['host'] ?? '';

            // Данные авторизации
            $this->username = $settings['username'] ?? '';
            $this->password = $settings['password'] ?? '';

            // Проект в системе Polyaxon
            $this->project = $settings['project'] ?? '';
        }

        // Авторизация, получение токена
        if (!$this->auth()) {
            // Лог ошибки
            return $this->errors;
        }
    }

    /**
     * Авторизация
     * Сохраняет полученный токен
     *
     * @return bool
     */
    private function auth(): bool {

        // Данные авторизации
        $auth_data = [
            'username' => $this->username,
            'password' => $this->password,
        ];

        // Точка входа
        $endpoint = 'users/token';

        // Запрос авторизации
        $response = $this->request($endpoint, $auth_data, 'POST');

        // Сохранение токена
        if (isset($response['token']) && !empty($response['token'])){
            $this->token = $response['token'];
        } else {
            $this->errors[] = json_encode($response);
            return false;
        }
        return true;
    }

    /**
     * Выполнение запроса АПИ
     *
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array
     */
    private function request(string $endpoint, array $data = [], string $method = 'GET'): array {

        // http-клиент
        $client = new Client();

        // Не указан адрес хоста
        if (empty($this->host)) {
            $this->errors[] = 'Проверьте корректность адреса хоста (host) в настройке "polyaxon".';
            return [];
        }

        // Если получен токен добавляется заголовок авторизации и кодировки
        if ($this->token) {
            $this->headers['Authorization'] = $this->token_type .' '. $this->token;
            $this->headers['Accept-Encoding'] = 'gzip,deflate';
        }

        // Адрес запроса
        $url = $this->host . '/api/' . self::VERSION . '/' . $endpoint;

        try {
            // Отправка запроса
            $response = $client->createRequest()
                ->setMethod($method)
                ->setOptions(['timeout' => 60])
                ->setFormat(Client::FORMAT_JSON)
                ->setUrl($url)
                ->addHeaders($this->headers)
                ->setData($data)
                ->send();

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            return $this->errors;
        }

        // Получение контента запроса
        $data = $response->content;

        // Пустое тело ответа (пример: запрос остановки эксперимента)
        if (empty($data)) return [];

        // Если ответа закодирован
        if (isset($response->headers['content-encoding']) && $response->headers['content-encoding'] == 'gzip') {

            // Если неопределена функция gzdecode
            if (!function_exists('gzdecode')) {
                function gzdecode($data) {
                    // Раскодировка данных inflate с предварительной обрезкой для получения сырых необработанных данных
                    return gzinflate(substr($data, 10, -8));
                }
            }

            // Декодирование данных
            $data = gzdecode($data);
        }

        // Преобразование JSON в массив и возврат результата
        return json_decode($data, true);
    }

    /**
     * Получение списка экспериментов
     * Выполняет запрос к API
     *
     * @param int $limit
     * @param string $sort
     * @return array|null
     */
    private function getExperiments(int $limit = 20, string $sort = ''): array {

        // Точка входа
        $endpoint = $this->project . '/experiments?limit='. $limit .'&sort='. $sort;

        // Выполнение запроса АПИ
        $response = $this->request($endpoint);

        // Если эксперименты не получены
        if (!isset($response['results']) || count($response['results']) < 1) {
            $this->errors[] = json_encode($response);
            return [];
        }

        // Возврат результата запроса
        return $response['results'];
    }

    /**
     * Получение данных эксперимента
     *
     * @param int $experiment_id
     * @return array
     */
    private function getExperiment(int $experiment_id): array {

        // Точка входа
        $endpoint = $this->project . '/experiments/' . $experiment_id;

        // Возврат результата запроса
        return $this->request($endpoint);
    }

    /**
     * Копирование эксперимента
     *
     * @param int $experiment_id
     * @param string $name
     * @return array
     */
    private function copyExperiment(int $experiment_id, string $name): array {

        // Точка входа
        $endpoint = $this->project . '/experiments/' . $experiment_id . '/copy';

        // Текущее время
        $date = new UTCDateTime(Carbon::now()->timestamp * 1000);
        $datetime = $date->toDateTime()->setTimeZone(new \DateTimeZone('Europe/Moscow'));
        $sync_time = $datetime->format(DATE_ATOM);

        // Параметры запроса
        $data = [
            'content' => [
                'params' => [
                    // Идентификатор выгрузки датасета для обучения
                    "sync_time" => $sync_time,
                    // Переменная для тестирования
                    "sample_size" =>  YII_DEBUG ? 1 : 0
                ]
            ],
            // Наименование эксперимента
            'description'=> $name
        ];

        // Возврат результата запроса
        return $this->request($endpoint, $data, 'POST');
    }

    /**
     * Получение успешного эксперимента
     *
     * @return int
     */
    private function getSuccessExperiment(): int {

        // Идентификатор эксперимента
        $experiment_id = 0;

        // Получение списка экспериментов
        $experiments = $this->getExperiments(100);

        // Перебор экспериментов
        foreach ($experiments as $experiment) {
            // Если эксперимент успешный возвращается его идентификатор
            if (isset($experiment['last_status']) && $experiment['last_status'] == 'succeeded') {
                $experiment_id = intval($experiment['id']);
                break;
            }
        }

        // Успешный эксперимент не определен
        if (!$experiment_id) {
            $this->errors[] = "Не получен идентификатор последнего успешного эксперимента";
        }

        // Возврат результата
        return $experiment_id;
    }

    /**
     * Получение сообщения об ошибке
     *
     * @param int $experiment_id
     * @return string
     */
    private function getErrorMessage(int $experiment_id): string {

        // Точка входа
        $endpoint = $this->project . '/experiments/' . $experiment_id . '/statuses';

        // Выполнение запроса на получение статусов эксперимента
        $response = $this->request($endpoint);

        // Если статусы эксперимента не получены
        if (!isset($response['results']) || count($response['results']) < 1) {
            $this->errors[] = json_encode($response);
            return '';
        }

        // Вовзрат результата
        return $response['results'][count($response['results'])-1]['message'] ?? '';
    }

    /**
     * Запуск обучения
     * Копирует последний успешный эксперимент
     *
     * @param string $name
     * @return bool
     */
    public function start(string $name): bool {

        // Получение идентификатора последнего успешного эксперимента
        $experiment_id = $this->getSuccessExperiment();

        // Копирование эксперимента
        $new_experiment = $this->copyExperiment($experiment_id, $name);

        // Возврат результата
        return isset($new_experiment['results']) ? true : false;
    }

    /**
     * Остановка эксперимента
     *
     * @param int $experiment_id
     * @return array
     */
    public function stop(int $experiment_id): array {

        // Точка входа
        $endpoint = $this->project . '/experiments/' . $experiment_id . '/stop';

        // Выполнение запроса остановки эксперимента
        $this->request($endpoint, [], 'POST');

        // Возвращение данных эксперимента
        return $this->check($experiment_id);
    }

    /**
     * Получение списка экспериментов и подготовка данных
     *
     * @return array
     */
    public function list(): array {

        // Массив данных экспериментов
        $experiments_data = [];

        // Получение экспериментов
        $experiments = $this->getExperiments();

        // Перебор экспериментов
        foreach ($experiments as $experiment) {

            // Даты начала и завершения эксперимента
            $date_start = Carbon::parse($experiment['created_at'])->toDateTimeString();
            $date_end = isset($experiment['finished_at']) ? Carbon::parse($experiment['finished_at'])->toDateTimeString() : null;

            // Если получен неопределенный статус эксперимента
            if (in_array($experiment['last_status'], ['warning', 'unschedulable', 'unknown'])) {
                // Получение сообщения об ошибке в системе Polyaxon
                $error_message = $this->getErrorMessage($experiment['id']);
            } else {
                $error_message = null;
            }

            // Если один из экспериментов в работе
            if (!in_array($experiment['last_status'], ['stopped', 'succeeded', 'failed', 'upstream_failed', 'skipped']) && !isset($experiments_data['running'])) {
                $experiments_data['running'] = $experiment['id'];
            }

            // Сбор данных
            $experiments_data['data'][$experiment['uuid']] = [
                'id' => $experiment['id'],
                'status' => $experiment['last_status'],
                'status_text' => \Yii::t('app', $this->statuses[isset($this->statuses[$experiment['last_status']]) ? $experiment['last_status'] : 'unknown']),
                'name' => !empty($experiment['description']) ? $experiment['description'] : '',
                'project' => str_replace('iqtools.','', $experiment['project']),
                'date_start' => $date_start,
                'date_end' => $date_end,
                'step' => $experiment['last_metric']['step'] ?? null,
                'percent' => isset($experiment['last_metric']['train_net_percentage']) ? intval($experiment['last_metric']['train_net_percentage']) : 0,
                'error_message' => $error_message
            ];
        }

        // Возврат результата
        return $experiments_data;
    }

    /**
     * Получение и формат данных эксперимента
     *
     * @param int $experiment_id
     * @return array
     */
    public function check(int $experiment_id): array {

        // Получение данных
        $experiment = $this->getExperiment($experiment_id);

        // Если данные не получены
        if (!isset($experiment['id']) || empty($experiment['id'])) {
            $this->errors[] = json_encode($experiment);
            return [];
        }

        // Если получен неопределенный статус эксперимента
        if (in_array($experiment['last_status'], ['warning', 'unschedulable', 'unknown'])) {
            // Получение сообщения об ошибке в системе Polyaxon
            $error_message = $this->getErrorMessage($experiment['id']);
        }

        // Возврат результата
        return [
            'id' => $experiment['id'],
            'uuid' => $experiment['uuid'],
            'status' => $experiment['last_status'],
            'status_text' => \Yii::t('app', $this->statuses[isset($this->statuses[$experiment['last_status']]) ? $experiment['last_status'] : 'unknown']),
            'name' => !empty($experiment['description']) ? $experiment['description'] : '',
            'step' => $experiment['last_metric']['step'] ?? null,
            'percent' => isset($experiment['last_metric']['train_net_percentage']) ? intval($experiment['last_metric']['train_net_percentage']) : 0,
            'error_message' => $error_message ?? null,
        ];
    }
}
