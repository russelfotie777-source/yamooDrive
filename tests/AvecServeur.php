<?php

namespace Yamoo\Tests;

// Lance le vrai serveur de développement (php -S index.php) sur un port libre, avec la
// base jetable des tests et un dossier de sessions à lui, effacé à la fin.
trait AvecServeur
{
    private static $processus;
    private static string $adresse;
    private static string $sessions;

    public static function setUpBeforeClass(): void
    {
        $prise = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($prise, false), ':'), 1);
        fclose($prise);
        self::$adresse = "http://127.0.0.1:$port";
        self::$sessions = sys_get_temp_dir() . '/yamoo-sessions-' . bin2hex(random_bytes(6));
        mkdir(self::$sessions, 0700);

        $racine = dirname(__DIR__);
        self::$processus = proc_open(
            [PHP_BINARY, '-d', 'session.save_path=' . self::$sessions, '-S', "127.0.0.1:$port", '-t', $racine, "$racine/index.php"],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $tuyaux,
            $racine,
            getenv() + ['APP_DEBUG' => '0']
        );
        for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
            usleep(20000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$processus);
        proc_close(self::$processus);
        array_map('unlink', glob(self::$sessions . '/*') ?: []);
        rmdir(self::$sessions);
    }

    /** Cookie de session posé par une réponse, à renvoyer avec les demandes suivantes. */
    private static function cookieDeSession(array $entetes): ?string
    {
        $cookie = null;
        foreach ($entetes as $ligne) {
            if (preg_match('/^Set-Cookie: (yamoo=[^;]+)/i', $ligne, $m)) {
                $cookie = $m[1];
            }
        }
        return $cookie;
    }

    /**
     * GET, ou POST quand un formulaire est donné. Les redirections ne sont pas suivies.
     * @param list<string> $entetes en-têtes de plus, comme « X-Forwarded-Proto: https »
     * @return array{int, string, list<string>} code, corps, en-têtes
     */
    private function demande(string $chemin, ?array $formulaire = null, ?string $cookie = null, array $entetes = []): array
    {
        $http = ['ignore_errors' => true, 'timeout' => 5, 'follow_location' => 0, 'header' => $entetes];
        if ($cookie !== null) {
            $http['header'][] = "Cookie: $cookie";
        }
        if ($formulaire !== null) {
            $http['method'] = 'POST';
            $http['header'][] = 'Content-Type: application/x-www-form-urlencoded';
            $http['content'] = http_build_query($formulaire);
        }
        $corps = file_get_contents(self::$adresse . $chemin, false, stream_context_create(['http' => $http]));
        $entetes = $http_response_header ?? [];
        preg_match('#^HTTP/\S+ (\d{3})#', $entetes[0] ?? '', $m);
        return [(int) ($m[1] ?? 0), (string) $corps, $entetes];
    }
}
