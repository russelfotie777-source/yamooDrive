<?php

namespace Yamoo\Assistant;

use Anthropic\Beta\Messages\BetaToolUseBlock;
use Anthropic\Client;

// Conversation avec Claude et ses outils, dans une boucle gérée ici.
final class Claude
{
    private const TOURS_MAX = 6;

    public function __construct(private string $cle)
    {
    }

    /**
     * @param list<array{role: string, texte: string}> $historique
     * @return array{texte: string, cartes: array, action: ?array}
     */
    public function reponds(array $historique, string $contexte): array
    {
        $client = new Client(apiKey: $this->cle, requestOptions: ['timeout' => 45, 'maxRetries' => 1]);
        $outils = new Outils();
        $messages = [];
        foreach ($historique as $i => $m) {
            $dernier = $i === array_key_last($historique);
            $contenu = $dernier
                ? [['type' => 'text', 'text' => $contexte], ['type' => 'text', 'text' => $m['texte']]]
                : $m['texte'];
            $messages[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $contenu];
        }

        $definitions = Outils::definitions();
        for ($tour = 0; $tour < self::TOURS_MAX; $tour++) {
            $reponse = $client->beta->messages->create(
                model: config('assistant.modele'),
                maxTokens: 4096,
                system: [['type' => 'text', 'text' => Consignes::texte(), 'cacheControl' => ['type' => 'ephemeral']]],
                messages: $messages,
                tools: $definitions,
                outputConfig: ['effort' => config('assistant.effort')],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
            $arret = $reponse->stopReason instanceof \BackedEnum ? $reponse->stopReason->value : (string) $reponse->stopReason;

            if ($arret === 'refusal') {
                return ['texte' => t('Je ne peux pas répondre à cette demande. L’équipe Yamoo Drive vous répond sur WhatsApp.'), 'cartes' => [], 'action' => null];
            }
            if ($arret !== 'tool_use') {
                return ['texte' => self::texte($reponse->content), 'cartes' => $outils->cartes, 'action' => $outils->action];
            }

            $resultats = [];
            foreach ($reponse->content as $bloc) {
                if ($bloc instanceof BetaToolUseBlock) {
                    $sortie = $outils->execute($bloc->name, $bloc->input);
                    $resultats[] = [
                        'type' => 'tool_result',
                        'toolUseID' => $bloc->id,
                        'content' => json_encode($sortie, JSON_UNESCAPED_UNICODE),
                        'isError' => isset($sortie['erreur']),
                    ];
                }
            }
            $messages[] = ['role' => 'assistant', 'content' => $reponse->content];
            $messages[] = ['role' => 'user', 'content' => $resultats];
        }
        return ['texte' => t('Je n’ai pas réussi à finir la recherche. Reformulez votre question ou écrivez-nous sur WhatsApp.'), 'cartes' => $outils->cartes, 'action' => $outils->action];
    }

    private static function texte(array $contenu): string
    {
        $morceaux = [];
        foreach ($contenu as $bloc) {
            if (($bloc->type ?? '') === 'text') {
                $morceaux[] = $bloc->text;
            }
        }
        return trim(implode("\n\n", $morceaux));
    }
}
