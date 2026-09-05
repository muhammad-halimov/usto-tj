<?php

namespace App\Controller\Api\CRUD\POST\TechSupport\Telegram;

use App\Controller\Api\CRUD\Abstract\AbstractApiHelperController;
use App\Entity\TechSupport\TicketApproval;
use App\Entity\User;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Telegram\TelegramDriver;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TelegramBotController extends AbstractApiHelperController
{
    #[Route('/webhook', name: 'bot_webhook', methods: ['POST'])]
    public function webhook(): Response
    {
        $config = [
            "telegram" => [
                "token" => $_ENV['TELEGRAM_BOT_TOKEN']
            ]
        ];

        DriverManager::loadDriver(TelegramDriver::class);
        $botman = BotManFactory::create($config);

        $botman->hears(['/start', 'start'], function (BotMan $bot) {
            $bot->reply('👋 Привет! Бот для уведомлений ТП запущен | ustoyob.tj');
        });

        $botman->hears(['/id', 'id'], function (BotMan $bot) {
            $bot->reply("🆔 ID чата: {$bot->getUser()->getId()}");
        });

        // Кнопка "✅ Подтвердить" под уведомлением о новой/изменённой
        // заявке (см. NotifyNewTicketApprovalTelegramBotService) — тот же
        // callback_data, что зашит в кнопку. BotMan сам вытаскивает
        // callback_query.data как "текст" входящего сообщения (см.
        // TelegramDriver::loadMessages()), поэтому обычный hears() с
        // {id}-плейсхолдером ловит и его, не только реальные текстовые
        // команды. getUser()->getId() в этом случае — это callback_query.
        // from.id, то есть ID того, кто НАЖАЛ кнопку, а не кому изначально
        // отправлено уведомление — обязательно перепроверяем права, а не
        // доверяем самому факту "кто-то нажал кнопку в этом чате".
        $botman->hears('approve_ticket_approval:{id}', function (BotMan $bot, string $id) {
            $admin = $this->entityManager->getRepository(User::class)
                ->findOneBy(['telegramChatId' => (string) $bot->getUser()->getId()]);

            if (!$admin || !in_array('ROLE_ADMIN', $admin->getRoles(), true)) {
                $bot->reply('⛔ У вас нет прав подтверждать заявки.');
                return;
            }

            $approval = $this->entityManager->getRepository(TicketApproval::class)->find((int) $id);

            if (!$approval) {
                $bot->reply('⚠️ Заявка не найдена — возможно, уже удалена.');
                return;
            }

            if ($approval->isApproved()) {
                $bot->reply('ℹ️ Уже подтверждено ранее.');
                return;
            }

            // Тот же setApproved(), что и переключатель поля в EasyAdmin /
            // TicketApprovalCrudController::batchApprove() — вся защитная
            // логика (забаненный тикет, повторная установка true) там же,
            // не дублируем.
            $approval->setApproved(true);
            $this->entityManager->flush();

            $bot->reply('✅ Подтверждено! Объявление/услуга снова видны публично.');
        });

        $botman->fallback(function (BotMan $bot) {
            $bot->reply('Попробуй: /start, /id');
        });

        $botman->listen();

        return new Response('OK', Response::HTTP_OK);
    }
}
