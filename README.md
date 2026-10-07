# Hotel Reservations API

API REST em Laravel para gestão de hotéis, quartos, reservas e pagamentos, desenvolvida para o desafio técnico da Foco Multimídia.

O que o sistema faz:

- importa hotéis, quartos e reservas de arquivos XML, por um comando agendado (cron);
- oferece um CRUD de quartos;
- cria reservas, verificando se o quarto está livre no período;
- registra e estorna pagamentos das reservas, calculando o saldo.

Todas as respostas da API são em JSON.

## Tecnologias

- PHP 8.4 e Laravel 13
- MySQL 8
- Docker e Docker Compose
- PHPUnit (testes automatizados)

## Como rodar

Pré-requisito: Docker Desktop.

```bash
git clone https://github.com/glediston/hotel-reservations-api.git
cd hotel-reservations-api
cp .env.example .env
docker compose build
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan import:xml
```

- No PowerShell, troque `cp` por `Copy-Item .env.example .env`.
- O `import:xml` carrega os dados dos XMLs (3 hotéis, 6 quartos e as reservas).

A API fica em `http://localhost:8000/api`. O MySQL fica exposto na porta `3307` (usuário `hotel`, senha `secret`, banco `hotel_reservas`).

Para zerar o banco e recarregar tudo:

```bash
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan import:xml
```

Serviços do `docker-compose.yml`:

| Serviço | Função |
|---|---|
| `app` | Servidor da API (`php artisan serve`) na porta 8000 |
| `scheduler` | Executa o agendador do Laravel (`schedule:work`), que dispara a importação |
| `db` | MySQL 8 |

## Modelagem do banco

O diagrama está em [docs/database/diagram.md](docs/database/diagram.md).

Tabelas: `hotels`, `rooms`, `reserves`, `guests`, `dailies` e `payments`.

- Um hotel tem vários quartos.
- Uma reserva pertence a um hotel e a um quarto, e tem vários hóspedes, diárias e pagamentos.
- As tabelas `hotels`, `rooms` e `reserves` têm a coluna `external_id`, que guarda o id vindo do XML. Assim a importação identifica o que já existe sem depender dos ids do banco.

## Importação dos XMLs

Os arquivos ficam em `database/xml/` (`hotels.xml`, `rooms.xml` e `reserves.xml`).

Execução manual:

```bash
docker compose exec app php artisan import:xml
```

Para usar outra pasta (caminho relativo à raiz do projeto): `php artisan import:xml --path=outra/pasta`.

### Execução via cron

O comando está agendado em `routes/console.php` para rodar **todos os dias às 02:00**, sem sobreposição. No Docker, o serviço `scheduler` já executa o agendador continuamente, então não é preciso configurar nada.

Fora do Docker, basta uma linha no cron do servidor:

```
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Para ver os agendamentos: `docker compose exec app php artisan schedule:list`.

### Regras da importação

- **Idempotente:** pode rodar várias vezes sem duplicar dados. Hotéis, quartos e reservas são atualizados pelo `external_id`. Hóspedes, diárias e pagamentos de cada reserva são recriados a cada execução.
- Cada reserva é gravada em **transação**: ou entra completa, ou não entra.
- Dados inconsistentes não interrompem a importação. Viram aviso no terminal e linha em `storage/logs/laravel.log`:
  - diária com data fora do período da reserva é **ignorada** (ocorre na reserva 6 do XML);
  - soma das diárias diferente do total informado gera aviso (a reserva é importada mesmo assim);
  - reserva cujo quarto não pertence ao hotel informado é ignorada.
- Reservas sem o bloco `<Payments>` (como a reserva 2) são importadas sem pagamentos.

## Endpoints

Os exemplos usam `curl` no **Git Bash** (ou Linux/macOS). No PowerShell do Windows, `curl` é outro comando e os exemplos não funcionam; use o Git Bash, o Postman ou o Insomnia.

### Códigos de resposta

| Código | Quando acontece |
|---|---|
| `200` | Consulta ou alteração feita |
| `201` | Registro criado |
| `204` | Registro excluído (sem corpo) |
| `404` | Registro não encontrado |
| `409` | Conflito: quarto já reservado no período, ou exclusão de quarto que tem reservas |
| `422` | Erro de validação (o campo `errors` diz o que está errado) |

### Quartos (CRUD)

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/rooms` | Lista paginada (15 por página). Filtro opcional: `?hotel_id=1` |
| GET | `/api/rooms/{id}` | Detalha um quarto |
| POST | `/api/rooms` | Cadastra um quarto |
| PUT/PATCH | `/api/rooms/{id}` | Altera o nome do quarto |
| DELETE | `/api/rooms/{id}` | Exclui o quarto |

Cadastrar um quarto:

```bash
curl -X POST http://localhost:8000/api/rooms \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"hotel_id": 1, "name": "Suite Master"}'
```

Regras:

- O nome é único dentro de cada hotel (hotéis diferentes podem ter quartos com o mesmo nome).
- Só o nome pode ser alterado. Mudar o hotel de um quarto com reservas deixaria os dados inconsistentes.
- Um quarto que possui reservas não pode ser excluído (`409`).

### Reservas

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/reserves` | Cria uma reserva |
| GET | `/api/reserves/{id}` | Detalha uma reserva (hóspedes, diárias, pagamentos e saldo) |

Criar uma reserva:

```bash
curl -X POST http://localhost:8000/api/reserves \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
    "room_id": 1,
    "check_in": "2026-12-20",
    "check_out": "2026-12-23",
    "daily_value": 100,
    "guests": [{"name": "Maria", "last_name": "Souza", "phone": "5571999999999"}]
  }'
```

Use datas futuras: check-in no passado é recusado.

Resposta (`201`): a reserva com `total`, `paid` (pago), `balance` (saldo) e `payment_status`.

Regras de negócio:

- O **hotel** vem do quarto, então basta informar o `room_id`.
- O sistema gera **uma diária por noite** com o valor de `daily_value` e calcula o **total**. O total nunca é informado pelo cliente. Ex.: 20/12 a 23/12 são 3 diárias.
- **Por que o `daily_value` vem na requisição:** os XMLs não trazem preço por quarto (o valor está só nas diárias de cada reserva), então o quarto não tem um preço cadastrado.
- **Disponibilidade:** se o quarto já estiver reservado em qualquer parte do período, retorna `409` com a mensagem `Este quarto já está reservado de DD/MM/AAAA a DD/MM/AAAA.` O dia do check-out fica livre para um novo check-in.
- O check-out deve ser depois do check-in.
- É obrigatório informar ao menos um hóspede.
- A criação usa transação e trava o quarto (`lockForUpdate`), evitando que duas requisições simultâneas reservem o mesmo quarto.

### Pagamentos

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/reserves/{id}/payments` | Registra um pagamento na reserva |
| DELETE | `/api/payments/{id}` | Estorna (remove) um pagamento |

Registrar um pagamento:

```bash
curl -X POST http://localhost:8000/api/reserves/1/payments \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"method": 2, "value": 100}'
```

As duas rotas devolvem a reserva atualizada, com o novo saldo. Para consultar os pagamentos de uma reserva, use `GET /api/reserves/{id}`. O `id` de cada pagamento (usado no estorno) aparece na lista `payments` da reserva.

Regras:

- O pagamento pode ser parcial (sinal) e a reserva pode receber vários pagamentos.
- Não é possível pagar mais do que o saldo da reserva (`422`).
- `payment_status` é calculado: `pendente` (nada pago), `parcial` (pago menos que o total) ou `quitado`.
- Formas de pagamento: o XML traz apenas o número do método (`Method 1`), sem explicar o significado. **Suposição adotada:** `1 = dinheiro`, `2 = pix`, `3 = cartão`. A tabela está em `Payment::METHODS`.

## Logs

Ficam em `storage/logs/laravel.log`. São registrados: criação de reserva, pagamento registrado, pagamento estornado e avisos da importação.

## Testes

```bash
docker compose exec app php artisan test
```

Os testes usam SQLite em memória (configurado no `phpunit.xml`), então não afetam o banco MySQL de desenvolvimento.

Cobertura:

- CRUD de quartos;
- importação dos XMLs (idempotência, diária fora do período, reserva sem pagamento);
- criação de reservas (cálculo, conflitos de data, validações);
- pagamentos (parcial, quitado, saldo excedido, estorno).

## Organização do código

| Arquivo / pasta | Responsabilidade |
|---|---|
| `app/Console/Commands/ImportXml.php` | Comando de importação dos XMLs |
| `app/Services/ReservationService.php` | Regras da reserva: disponibilidade, diárias e total |
| `app/Models/Reserve.php` | Cálculo do valor pago, do saldo e do status do pagamento |
| `app/Http/Controllers/Api/` | Controllers de autenticação, quartos, reservas e pagamentos |
| `app/Http/Requests/` | Validação dos dados recebidos |
| `app/Http/Resources/` | Formato das respostas JSON |
| `app/Exceptions/RoomUnavailableException.php` | Erro de quarto ocupado, devolvido como `409` |
| `routes/api.php` | Rotas da API |
| `routes/console.php` | Agendamento do cron |

## Fluxo de Git

- Uma branch por funcionalidade (`feat/...`, `test/...`, `docs/...`), integrada na `main` por merge.
- Commits no padrão Conventional Commits (`feat`, `fix`, `test`, `docs`, `chore`).
