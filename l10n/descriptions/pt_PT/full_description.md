# Aplicação PhoneTrack Nextcloud

📱 PhoneTrack é uma aplicação Nextcloud para rastreamento e armazenamento de localizações de dispositivos móveis.

🗺 Recebe informações de aplicações de registo de telemóveis e apresenta-as dinamicamente num mapa.

🌍 Ajude-nos a traduzir esta aplicação no [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

Veja outras formas de ajudar no [guia para contribuições](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Como usar o PhoneTrack :

- Criar uma sessão de rastreamento.
- Forneca o link\* de registo ao dispositivo móvel. Choose the [logging method](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#logging-methods) you prefer.
- Observe a sessão de localização do dispositivo em tempo real (ou não) no PhoneTrack ou partilhe através das páginas públicas.

(\*) Não se esqueça de definir o nome do dispositivo no link (ao invés de nas definições da aplicação de registo). Substitua "o seu nome" pelo nome de dispositivo desejado.
Definir o nome de dispositivo nas definições da aplicação de registo apenas funcionam com o Owntracks, Traccar e OpenGTS.

Na página principal do PhoneTrack, durante uma sessão, é possível:

- 📍 Visualizar histórico de localizações
- ⛛ Filtrar pontos
- ✎ Adicionar/editar/remover pontos
- ✎ Editar dispositivos (renomear, mudar cor/formato, mover para outra sessão)
- ⛶ Definir delimitações geográficas para dispositivos
- ⚇ Definir alertas de proximidade para dispositivos emparelhados
- 🖧 Partilhar uma sessão com outros utilizadores Nextcloud ou através de um link público (apenas leitura)
- 🔗 Gerar links de partilha públicos com restrições opcionais (filtros, nome do dispositivo, apenas última localização, simplificação de delimitação geográfica)
- 🖫 Importar/exportar uma sessão no formato GPX (um ficheiro com um rastreamento por dispositivo ou um ficheiro por dispositivo)
- 🗠 Visualizar estatísticas da sessão
- 🔒 [Reserve a device name](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#device-name-reservation) to make sure only authorized user can log with this name
- 🗓 Alterar exportação e limpeza automática de sessão (diária/semanal/mensal)
- ◔ Escolher o que fazer quando o limite da quota do número de pontos é atingida (bloquear registo ou eliminar os pontos mais antigos)

A página pública e a página pública filtrada funcionam como a página principal mas apresentam apenas uma sessão, é tudo apenas de leitura e não é necessário iniciar sessão.

Esta aplicação encontra-se em desenvolvimento.

## Instalação

Consulte [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) para detalhes sobre a instalação.

Consulte o ficheiro [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) para verificar as novidades e futuras funcionalidades.

Consulte o ficheiro [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) para ver a lista completa de autores.

## Problemas conhecidos

- O PhoneTrack **já funciona** com as restrições de grupo do Nextcloud ativas. See [admindoc](https://github.com/julien-nc/phonetrack/blob/main/doc/admin.md#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Qualquer comentário será apreciado.

