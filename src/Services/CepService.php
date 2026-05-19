<?php
// =============================================================
// src/Services/CepService.php — Integração ViaCEP
// API pública, sem chave de API necessária
// =============================================================

namespace DojoManager\Services;

class CepService
{
    /**
     * Busca endereço pelo CEP usando a API ViaCEP (https://viacep.com.br)
     * @throws \RuntimeException se a API estiver indisponível
     */
    public static function buscar(string $cep): array
    {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) !== 8) {
            throw new \InvalidArgumentException('CEP deve ter 8 dígitos.');
        }

        $url = sprintf(VIACEP_URL, $cep);

        $ctx = stream_context_create([
            'http' => [
                'timeout'       => 5,
                'ignore_errors' => true,
                'header'        => "User-Agent: DojoManager/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $json = @file_get_contents($url, false, $ctx);

        if ($json === false) {
            throw new \RuntimeException('Não foi possível conectar ao serviço de CEP.');
        }

        $dados = json_decode($json, true);

        if (isset($dados['erro'])) {
            throw new \RuntimeException('CEP não encontrado.');
        }

        return [
            'cep'        => $dados['cep']        ?? '',
            'logradouro' => $dados['logradouro']  ?? '',
            'bairro'     => $dados['bairro']      ?? '',
            'cidade'     => $dados['localidade']  ?? '',
            'uf'         => $dados['uf']          ?? '',
        ];
    }
}
