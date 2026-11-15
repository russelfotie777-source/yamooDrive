<?php

namespace Yamoo\Assistant;

use Throwable;

final class Assistant
{
    /**
     * @param mixed $brut historique envoyé par la page : [{role, texte}, …]
     */
    public static function reponds(mixed $brut): array
    {
        $historique = self::nettoie($brut);
        if (!$historique || end($historique)['role'] !== 'user') {
            return ['texte' => t('Posez votre question, je vous réponds.'), 'cartes' => [], 'action' => null];
        }

        $_SESSION['assistant'] = ($_SESSION['assistant'] ?? 0) + 1;
        if ($_SESSION['assistant'] > config('assistant.messages_par_session')) {
            return ['texte' => t('Vous avez posé beaucoup de questions. Pour la suite, l’équipe vous répond sur WhatsApp au {numero}.', ['numero' => config('contact.telephone_affiche')]), 'cartes' => [], 'action' => null];
        }

        $cle = env('ANTHROPIC_API_KEY');
        if ($cle) {
            try {
                return self::propre((new Claude($cle))->reponds($historique, self::contexte()));
            } catch (Throwable $e) {
                error_log('Assistant Claude : ' . $e->getMessage());
            }
        }
        return self::propre((new Simple())->reponds($historique));
    }

    private static function contexte(): string
    {
        // Toujours en français pour le modèle, avec la langue dans laquelle répondre.
        return 'Nous sommes le ' . maintenant()->format('Y-m-d') . ' (' . maintenant()->format('l') . '), il est ' . maintenant()->format('H:i') . ' à Douala. '
            . 'Langue du client : ' . \Yamoo\Langue::info('nom') . ' (' . \Yamoo\Langue::$code . '). Réponds dans cette langue.';
    }

    private static function nettoie(mixed $brut): array
    {
        if (!is_array($brut)) {
            return [];
        }
        $propre = [];
        foreach (array_slice($brut, -12) as $m) {
            if (!is_array($m) || !in_array($m['role'] ?? '', ['user', 'assistant'], true) || !is_string($m['texte'] ?? null)) {
                continue;
            }
            $texte = trim(mb_substr($m['texte'], 0, 1200));
            if ($texte !== '') {
                $propre[] = ['role' => $m['role'], 'texte' => $texte];
            }
        }
        // L'API attend une conversation qui commence par l'utilisateur.
        while ($propre && $propre[0]['role'] !== 'user') {
            array_shift($propre);
        }
        return $propre;
    }

    private static function propre(array $r): array
    {
        $r['cartes'] = array_map(function ($c) {
            unset($c['prix_brut']);
            return $c;
        }, $r['cartes']);
        return $r;
    }
}
