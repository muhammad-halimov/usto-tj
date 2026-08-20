<?php

namespace App\DataFixture\Prod\Ticket;

use App\Entity\Extra\Translation;
use App\Entity\Ticket\Unit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * ЧИСТО прод-данные — не зависит от App\DataFixture\Dev. Привязка юнита
 * 'pieceless' к dev-тикетам (раньше жила прямо тут) переехала в
 * App\DataFixture\Dev\Additional\TicketCategoryLinkFixture.
 */
class UnitFixture extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['prod'];
    }

    public function load(ObjectManager $manager): void
    {
        $unitsData = [
            [
                'title' => 'м³', 'description' => 'Кубический метр', 'ref' => 'cubemeter',
                'translations' => [
                    'ru'  => ['title' => 'м³',    'desc' => 'Кубический метр'],
                    'tj'  => ['title' => 'м³',    'desc' => 'Метри кубӣ'],
                    'eng' => ['title' => 'm³',    'desc' => 'Cubic metre'],
                ],
            ],
            [
                'title' => 'м²', 'description' => 'Квадратный метр', 'ref' => 'squaremeter',
                'translations' => [
                    'ru'  => ['title' => 'м²',    'desc' => 'Квадратный метр'],
                    'tj'  => ['title' => 'м²',    'desc' => 'Метри квадратӣ'],
                    'eng' => ['title' => 'm²',    'desc' => 'Square metre'],
                ],
            ],
            [
                'title' => 'м', 'description' => 'Погонный метр', 'ref' => 'meter',
                'translations' => [
                    'ru'  => ['title' => 'м',     'desc' => 'Погонный метр'],
                    'tj'  => ['title' => 'м',     'desc' => 'Метри тӯлонӣ'],
                    'eng' => ['title' => 'm',     'desc' => 'Linear metre'],
                ],
            ],
            [
                'title' => 'шт', 'description' => 'Поштучно', 'ref' => 'pieces',
                'translations' => [
                    'ru'  => ['title' => 'шт',    'desc' => 'Поштучно'],
                    'tj'  => ['title' => 'дона',  'desc' => 'Ба дона'],
                    'eng' => ['title' => 'pcs',   'desc' => 'Per piece'],
                ],
            ],
            [
                'title' => 'кг', 'description' => 'Килограмм', 'ref' => 'kg',
                'translations' => [
                    'ru'  => ['title' => 'кг',    'desc' => 'Килограмм'],
                    'tj'  => ['title' => 'кг',    'desc' => 'Килограмм'],
                    'eng' => ['title' => 'kg',    'desc' => 'Kilogram'],
                ],
            ],
            [
                'title' => 'т', 'description' => 'Тонна', 'ref' => 'ton',
                'translations' => [
                    'ru'  => ['title' => 'т',     'desc' => 'Тонна'],
                    'tj'  => ['title' => 'т',     'desc' => 'Тонна'],
                    'eng' => ['title' => 't',     'desc' => 'Ton'],
                ],
            ],
            [
                'title' => 'л', 'description' => 'Литр', 'ref' => 'liter',
                'translations' => [
                    'ru'  => ['title' => 'л',     'desc' => 'Литр'],
                    'tj'  => ['title' => 'л',     'desc' => 'Литр'],
                    'eng' => ['title' => 'l',     'desc' => 'Litre'],
                ],
            ],
            [
                'title' => 'комп.', 'description' => 'Комплект', 'ref' => 'kit',
                'translations' => [
                    'ru'  => ['title' => 'комп.', 'desc' => 'Комплект'],
                    'tj'  => ['title' => 'маҷм.', 'desc' => 'Маҷмӯа'],
                    'eng' => ['title' => 'set',   'desc' => 'Set / Kit'],
                ],
            ],
            [
                'title' => 'ч', 'description' => 'Час (почасовая оплата)', 'ref' => 'hour',
                'translations' => [
                    'ru'  => ['title' => 'ч',     'desc' => 'Час (почасовая оплата)'],
                    'tj'  => ['title' => 'соат',  'desc' => 'Соат (музди соатӣ)'],
                    'eng' => ['title' => 'hr',    'desc' => 'Hour (hourly rate)'],
                ],
            ],
            [
                'title' => 'день', 'description' => 'День (посуточная оплата)', 'ref' => 'day',
                'translations' => [
                    'ru'  => ['title' => 'день',  'desc' => 'День (посуточная оплата)'],
                    'tj'  => ['title' => 'рӯз',   'desc' => 'Рӯз (музди рӯзона)'],
                    'eng' => ['title' => 'day',   'desc' => 'Day (daily rate)'],
                ],
            ],
            [
                'title' => 'н/у', 'description' => 'Без единицы', 'ref' => 'pieceless',
                'translations' => [
                    'ru'  => ['title' => 'н/у',   'desc' => 'Без единицы измерения'],
                    'tj'  => ['title' => 'б/в',   'desc' => 'Бе воҳиди ченак'],
                    'eng' => ['title' => 'N/A',   'desc' => 'No unit of measurement'],
                ],
            ],
            [
                'title' => 'смена', 'description' => 'Смена (посменная оплата)', 'ref' => 'shift',
                'translations' => [
                    'ru'  => ['title' => 'смена', 'desc' => 'Смена (посменная оплата)'],
                    'tj'  => ['title' => 'сменa', 'desc' => 'Смена (музди сменагӣ)'],
                    'eng' => ['title' => 'shift',  'desc' => 'Shift (per-shift rate)'],
                ],
            ],
            [
                'title' => 'выезд', 'description' => 'Выезд (разовый вызов мастера)', 'ref' => 'callout',
                'translations' => [
                    'ru'  => ['title' => 'выезд', 'desc' => 'Выезд (разовый вызов мастера)'],
                    'tj'  => ['title' => 'ташриф', 'desc' => 'Ташриф (даъвати якдафъаина)'],
                    'eng' => ['title' => 'callout', 'desc' => 'Callout (one-time service visit)'],
                ],
            ],
            [
                'title' => 'проект', 'description' => 'Проект (фиксированная цена за весь объём работ)', 'ref' => 'project',
                'translations' => [
                    'ru'  => ['title' => 'проект', 'desc' => 'Проект (фиксированная цена за весь объём работ)'],
                    'tj'  => ['title' => 'лоиҳа',  'desc' => 'Лоиҳа (нархи собит барои тамоми ҳаҷми кор)'],
                    'eng' => ['title' => 'project', 'desc' => 'Project (fixed price for the whole scope of work)'],
                ],
            ],
            [
                'title' => 'упак.', 'description' => 'Упаковка', 'ref' => 'pack',
                'translations' => [
                    'ru'  => ['title' => 'упак.', 'desc' => 'Упаковка'],
                    'tj'  => ['title' => 'бастабандӣ', 'desc' => 'Бастабандӣ'],
                    'eng' => ['title' => 'pack',  'desc' => 'Pack / package'],
                ],
            ],
            [
                'title' => 'точка', 'description' => 'За точку (розетка, светильник и т.п.)', 'ref' => 'point',
                'translations' => [
                    'ru'  => ['title' => 'точка', 'desc' => 'За точку (розетка, светильник и т.п.)'],
                    'tj'  => ['title' => 'нуқта',  'desc' => 'Барои як нуқта (розетка, чароғ ва ғ.)'],
                    'eng' => ['title' => 'point',  'desc' => 'Per point (outlet, fixture, etc.)'],
                ],
            ],
            [
                'title' => 'окно', 'description' => 'За окно', 'ref' => 'window',
                'translations' => [
                    'ru'  => ['title' => 'окно',  'desc' => 'За окно'],
                    'tj'  => ['title' => 'тиреза', 'desc' => 'Барои як тиреза'],
                    'eng' => ['title' => 'window', 'desc' => 'Per window'],
                ],
            ],
            [
                'title' => 'дверь', 'description' => 'За дверь', 'ref' => 'door',
                'translations' => [
                    'ru'  => ['title' => 'дверь', 'desc' => 'За дверь'],
                    'tj'  => ['title' => 'дар',    'desc' => 'Барои як дар'],
                    'eng' => ['title' => 'door',  'desc' => 'Per door'],
                ],
            ],
            [
                'title' => 'чел.', 'description' => 'За человека', 'ref' => 'person',
                'translations' => [
                    'ru'  => ['title' => 'чел.',  'desc' => 'За человека'],
                    'tj'  => ['title' => 'нафар',  'desc' => 'Барои як нафар'],
                    'eng' => ['title' => 'person', 'desc' => 'Per person'],
                ],
            ],
            [
                'title' => 'сеанс', 'description' => 'Сеанс/сессия (массаж, тренировка, консультация)', 'ref' => 'session',
                'translations' => [
                    'ru'  => ['title' => 'сеанс', 'desc' => 'Сеанс/сессия (массаж, тренировка, консультация)'],
                    'tj'  => ['title' => 'сеанс',  'desc' => 'Сеанс (массаж, машқ, машварат)'],
                    'eng' => ['title' => 'session','desc' => 'Session (massage, workout, consultation)'],
                ],
            ],
            [
                'title' => 'стр.', 'description' => 'За страницу (перевод, вёрстка)', 'ref' => 'page',
                'translations' => [
                    'ru'  => ['title' => 'стр.',  'desc' => 'За страницу (перевод, вёрстка)'],
                    'tj'  => ['title' => 'саҳ.',   'desc' => 'Барои як саҳифа (тарҷума, чопгарӣ)'],
                    'eng' => ['title' => 'page',  'desc' => 'Per page (translation, layout)'],
                ],
            ],
        ];

        $refs = [];
        foreach ($unitsData as $data) {
            $unit = new Unit();
            $unit->setTitle($data['title']);
            $unit->setDescription($data['description']);

            foreach ($data['translations'] as $locale => $trans) {
                $translation = (new Translation())
                    ->setLocale($locale)
                    ->setTitle($trans['title'])
                    ->setDescription($trans['desc']);
                $unit->addTranslation($translation);
            }

            $manager->persist($unit);
            $refs[$data['ref']] = $unit;
        }

        foreach ($refs as $ref => $unit) {
            $this->addReference($ref, $unit);
        }

        $manager->flush();
    }
}
