# Aplicativo PhoneTrack para Nextcloud

📱 PhoneTrack é um aplicativo para Nextcloud para localizar e salvar a localização de dispositivos móveis.

🗺 Recebe informações de aplicativos de registro em telefones celulares e mostra dinamicamente em um mapa.

🌍 Ajude-nos a traduzir este aplicativo no [projeto PhoneTrack no Crowdin](https://crowdin.com/project/phonetrack).

⚒Confira outras maneiras de ajudar nas [diretrizes de contribuição](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Como usar o PhoneTrack :

* Crie uma sessão de rastreamento.
* Dê o link de registro* para os dispositivos móveis. Escolha o método de registro de sua preferência.
* Assista a localização dos dispositivos da sessão em tempo real (ou não) no PhoneTrack ou compartilhe-o com páginas públicas.

(\ *) Não se esqueça de definir o nome do dispositivo no link (e não nas configurações do aplicativo de log). Substitua "seunome" pelo nome do dispositivo desejado. A definição do nome do dispositivo nas configurações do aplicativo de registro funciona apenas com Owntracks, Traccar e OpenGTS.

Na página principal do PhoneTrack, enquanto assiste a uma sessão, você pode:

* 📍 Exibir histórico de localização
* ⛛ Filtrar pontos
* ✎ Editar / adicionar / excluir pontos manualmente
* ✎ Editar dispositivos (renomear, alterar cor / forma, mudar para outra sessão)
* ⛶ Definir zonas de cercas geográficas para dispositivos
* ⚇ Definir alertas de proximidade para pares de dispositivos
* 🖧 Compartilhe uma sessão com outros usuários Nextcloud ou com um link público (somente leitura)
* 🔗 Gere links de compartilhamento público com restrições opcionais (filtros, nome do dispositivo, apenas últimas posições, simplificação de cercas geográficas)
* 🖫 Importar / exportar uma sessão no formato GPX (um arquivo com uma faixa por dispositivo ou um arquivo por dispositivo)
* 🗠 Exibir estatísticas de sessões
* 🔒 [ Reserve um nome de dispositivo ](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) para garantir que apenas usuários autorizados possam fazer logon com este nome
* 🗓 Alternar exportação automática e limpeza automática da sessão (diariamente / semanalmente / mensalmente)
* ◔ Escolha o que fazer quando a cota do número de pontos for atingida (bloquear o registro ou excluir o ponto mais antigo)

A página pública e a página pública filtrada funcionam como a página principal, exceto que apenas uma sessão é exibida, tudo é somente leitura e não é necessário fazer login.

Este aplicativo foi testado no Nextcloud 17 com Firefox 57+ e Chromium.

Este aplicativo é compatível com cores temáticas e temas de acessibilidade!

Este aplicativo está em desenvolvimento.

## Instalação

Consulte o [ AdminDoc ](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) para obter detalhes da instalação.

Verifique o arquivo [ CHANGELOG ](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) para ver as novidades e as próximas versões.

Verifique o arquivo [ AUTHORS ](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) para ver a lista completa de autores.

## Problemas conhecidos

* O PhoneTrack ** agora funciona ** com a restrição de grupo Nextcloud ativada. Veja [ admindoc ](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Todos os comentários serão apreciados.