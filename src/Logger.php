<?php

namespace Logsfori;

class Logger
{
    // On peut renommer la constante par défaut pour la distinguer de la propriété modifiable.
    const DEFAULT_ENDPOINT = 'http://127.0.0.1:3000';

    const SEVERITY_DEBUG    = 'debug';
    const SEVERITY_INFO     = 'info';
    const SEVERITY_WARNING  = 'warning';
    const SEVERITY_ERROR    = 'error';
    const SEVERITY_CRITICAL = 'critical';

    const ALLOWED_SEVERITIES = [
        self::SEVERITY_DEBUG    => 0,
        self::SEVERITY_INFO     => 1,
        self::SEVERITY_WARNING  => 2,
        self::SEVERITY_ERROR    => 3,
        self::SEVERITY_CRITICAL => 4,
    ];

    // Propriété statique pour stocker le token d'authentification.
    protected static $token;

    // Niveau de sévérité minimum (par défaut INFO).
    protected static $minSeverity = self::SEVERITY_INFO;

    // Endpoint configurable, par défaut sur DEFAULT_ENDPOINT.
    protected static $endpoint = self::DEFAULT_ENDPOINT;

    /**
     * Initialise le token d'authentification et permet de surcharger l'endpoint.
     *
     * @param string $token    Le token d'authentification.
     * @param string $endpoint (Optionnel) L'URL de l'endpoint à utiliser.
     */
    public static function authenticate(string $token, string $endpoint = self::DEFAULT_ENDPOINT)
    {
        self::$token = $token;
        self::$endpoint = $endpoint;
    }

    /**
     * Permet de configurer la sévérité minimale pour l'envoi des logs.
     */
    public static function setMinimumSeverity(string $severity)
    {
        if (!array_key_exists($severity, self::ALLOWED_SEVERITIES)) {
            throw new \InvalidArgumentException('Invalid severity');
        }
        self::$minSeverity = $severity;
    }

    /**
     * Retourne la sévérité minimale configurée.
     */
    public static function getMinimumSeverity()
    {
        return self::$minSeverity;
    }

    /**
     * Envoie un log vers le serveur LogsForI.
     *
     * Appel en static :
     * Logger::push('event', 'message', Logger::SEVERITY_INFO);
     */
    public static function push(
        string $eventName,
        string $message,
        string $severity = self::SEVERITY_INFO,
        int $timestamp = null,
        array $extra = [],
        string $transactionId = null
    ) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty(self::$token)) {
            throw new \Exception('Token is required. Please call Logger::authenticate() first.');
        }

        self::validateSeverity($severity);

        if (self::ALLOWED_SEVERITIES[$severity] < self::ALLOWED_SEVERITIES[self::getMinimumSeverity()]) {
            return;
        }

        $timestamp = $timestamp ?? round(microtime(true) * 1000);
        if ($transactionId === null) {
            $transactionId = session_id();
        }

        $payload = [
            'transaction_id' => $transactionId,
            'token'          => self::$token,
            'event_name'     => $eventName,
            'message'        => $message,
            'severity'       => $severity,
            'created_at'     => $timestamp,
            'extra'          => $extra
        ];

        $curl = curl_init(self::$endpoint . '/push-log');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json'
            ],
        ]);
        curl_exec($curl);
        curl_close($curl);
    }

    /**
     * Valide que la sévérité passée est autorisée.
     */
    private static function validateSeverity(string $severity)
    {
        $allowed = [
            self::SEVERITY_DEBUG,
            self::SEVERITY_INFO,
            self::SEVERITY_WARNING,
            self::SEVERITY_ERROR,
            self::SEVERITY_CRITICAL
        ];

        if (!in_array($severity, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid severity');
        }
    }

    /**
     * Démarre un timer pour mesurer le temps d'exécution.
     */
    public static function startTimer(string $timerName)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['logsfori_timers'][$timerName] = microtime(true);
    }

    /**
     * Enregistre le temps d'exécution depuis le timer démarré.
     */
    public static function saveTimer(string $timerName)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['logsfori_timers'][$timerName])) {
            return null;
        }

        $executionTime = round((microtime(true) - $_SESSION['logsfori_timers'][$timerName]) * 1000, 2);
        unset($_SESSION['logsfori_timers'][$timerName]);

        if (empty(self::$token)) {
            throw new \InvalidArgumentException('Token is required. Please call Logger::authenticate() first.');
        }

        $payload = [
            'func_name'      => $timerName,
            'token'          => self::$token,
            'execution_time' => $executionTime,
            'created_at'     => round(microtime(true) * 1000),
        ];

        $curl = curl_init(self::$endpoint . '/timer');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json'
            ],
        ]);
        curl_exec($curl);
        curl_close($curl);
    }
}
