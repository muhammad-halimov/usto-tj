<?php

namespace App\DataFixture\Prod\Geography;

use App\Entity\Extra\Translation;
use App\Entity\Geography\District\District;
use App\Entity\Geography\District\Settlement;
use App\Entity\Geography\District\Village;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Settlements (посёлки/пгт) within districts, each containing villages.
 */
class SettlementFixture extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['prod'];
    }

    public function load(ObjectManager $manager): void
    {
        // [$settlementRef, $districtRef, $settlementTranslations, $settlementDesc, $villages]
        // $villages: [[$ref, $translations, $desc], ...]
        $settlementsData = [
            // ── ГРРП — Рудаки ──
            [
                'settlement_khushyor', 'rudaki',
                ['tj' => 'Хушёр', 'ru' => 'Хушьёр', 'eng' => 'Khushyor'],
                'Посёлок Хушьёр, район Рудаки, ГРРП',
                [
                    ['village_khushyor_poyon', ['tj' => 'Хушёри Поён', 'ru' => 'Хушьёр Нижний', 'eng' => 'Lower Khushyor'], 'Нижний Хушьёр'],
                    ['village_khushyor_bolo', ['tj' => 'Хушёри Боло',  'ru' => 'Хушьёр Верхний', 'eng' => 'Upper Khushyor'], 'Верхний Хушьёр'],
                ],
            ],
            [
                'settlement_navobod', 'rudaki',
                ['tj' => 'Навобод', 'ru' => 'Навобод', 'eng' => 'Navobod'],
                'Посёлок Навобод, район Рудаки, ГРРП',
                [
                    ['village_navobod_1', ['tj' => 'Навободи Боло', 'ru' => 'Навобод Верхний', 'eng' => 'Upper Navobod'], 'Навобод Верхний'],
                ],
            ],
            // ── Согдийская — Спитамен ──
            [
                'settlement_jumba', 'spitamen',
                ['tj' => 'Ҷумба', 'ru' => 'Джумба', 'eng' => 'Jumba'],
                'Посёлок Джумба, район Спитамен',
                [
                    ['village_chashma', ['tj' => 'Чашма', 'ru' => 'Чашма', 'eng' => 'Chashma'], 'Деха Чашма, джамоат Ширин'],
                ],
            ],
            // ── Хатлон — Вахш ──
            [
                'settlement_guliston', 'vakhsh_d',
                ['tj' => 'Гулистон', 'ru' => 'Гулистон', 'eng' => 'Guliston'],
                'ПГТ Гулистон, район Вахш, Хатлон',
                [
                    ['village_guliston_bolo', ['tj' => 'Гулистони Боло', 'ru' => 'Гулистон Верхний', 'eng' => 'Upper Guliston'], 'Гулистон Верхний'],
                    ['village_guliston_payon', ['tj' => 'Гулистони Поён', 'ru' => 'Гулистон Нижний', 'eng' => 'Lower Guliston'], 'Гулистон Нижний'],
                ],
            ],
            // ── Душанбе — Сино ──
            [
                'settlement_yovon', 'sino',
                ['tj' => 'Ёвон', 'ru' => 'Явон', 'eng' => 'Yovon'],
                'Посёлок Явон, район Сино, Душанбе',
                [
                    ['village_yovon_markaz', ['tj' => 'Марказ (Ёвон)', 'ru' => 'Центр (Явон)', 'eng' => 'Center (Yovon)'], 'Центр пос. Явон'],
                ],
            ],
            // ── ГБАО — Шугнан ──
            [
                'settlement_baroj', 'shugnan',
                ['tj' => 'Бароҷ', 'ru' => 'Барадж', 'eng' => 'Baroj'],
                'Посёлок Барадж, район Шугнан, ГБАО',
                [
                    ['village_baroj_1', ['tj' => 'Бароҷи Поён', 'ru' => 'Барадж Нижний', 'eng' => 'Lower Baroj'], 'Нижний Барадж'],
                ],
            ],
            // ── ГРРП — Гиссар ──
            [
                'settlement_kandak', 'hisor_d',
                ['tj' => 'Кандак', 'ru' => 'Кандак', 'eng' => 'Kandak'],
                'Посёлок Кандак, район Гиссар, ГРРП',
                [
                    ['village_kandak_1', ['tj' => 'Кандаки Боло', 'ru' => 'Кандак Верхний', 'eng' => 'Upper Kandak'], 'Верхний Кандак'],
                ],
            ],
            // ── Согдийская — Исфара ──
            [
                'settlement_surh', 'isfara_d',
                ['tj' => 'Сурх', 'ru' => 'Сурх', 'eng' => 'Surkh'],
                'Посёлок Сурх, район Исфара, Согдийская обл.',
                [
                    ['village_surh_1', ['tj' => 'Сурхи Поён', 'ru' => 'Сурх Нижний', 'eng' => 'Lower Surkh'], 'Нижний Сурх'],
                ],
            ],
            // ── Хатлон — Фархор ──
            [
                'settlement_pushing', 'farkhor_d',
                ['tj' => 'Пушинг', 'ru' => 'Пушинг', 'eng' => 'Pushing'],
                'Посёлок Пушинг, район Фархор, Хатлон',
                [
                    ['village_pushing_1', ['tj' => 'Пушинги Марказӣ', 'ru' => 'Пушинг Центральный', 'eng' => 'Central Pushing'], 'Центральный Пушинг'],
                ],
            ],
        ];

        foreach ($settlementsData as [$settlRef, $distRef, $settlTrans, $settlDesc, $villages]) {
            $settlement = new Settlement();
            $settlement->setDescription($settlDesc);

            foreach ($settlTrans as $locale => $title) {
                $settlement->addTranslation(
                    (new Translation())->setTitle($title)->setLocale($locale)->setAddress($settlement)
                );
            }

            /** @var District $district */
            $district = $this->getReference($distRef, District::class);
            $district->addSettlement($settlement);

            foreach ($villages as [$villRef, $villTrans, $villDesc]) {
                $village = new Village();
                $village->setDescription($villDesc);

                foreach ($villTrans as $locale => $title) {
                    $village->addTranslation(
                        (new Translation())->setTitle($title)->setLocale($locale)->setAddress($village)
                    );
                }

                $settlement->addVillage($village);
                $manager->persist($village);
                $this->addReference($villRef, $village);
            }

            $manager->persist($settlement);
            $this->addReference($settlRef, $settlement);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [DistrictFixture::class];
    }
}
