<?php

namespace App\DataFixture\Prod\Geography;

use App\Entity\Extra\Translation;
use App\Entity\Geography\District\District;
use App\Entity\Geography\Province\Province;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class DistrictFixture extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['prod'];
    }

    public function load(ObjectManager $manager): void
    {
        // [$ref, $translations, $desc, $provinceRef]
        $districtsData = [
            // ── Душанбе ──
            ['sino',       ['tj' => 'Сино',       'ru' => 'Сино',          'eng' => 'Sino'],          'Район Сино, Душанбе',          'dushanbe'],
            ['schevchenko',['tj' => 'Шевченко',   'ru' => 'Шевченко',      'eng' => 'Shevchenko'],    'Район Шевченко, Душанбе',      'dushanbe'],
            ['ismoil',     ['tj' => 'Исмоил',     'ru' => 'Исмоил Сомони', 'eng' => 'Ismoil Somoni'], 'Район Исмоил Сомони, Душанбе', 'dushanbe'],
            ['firdavsi_d', ['tj' => 'Фирдавсӣ',  'ru' => 'Фирдавси',      'eng' => 'Firdavsi'],      'Район Фирдавси, Душанбе',      'dushanbe'],
            // ── ГРРП ──
            ['rudaki',     ['tj' => 'Рудаки',     'ru' => 'Рудаки',        'eng' => 'Rudaki'],        'Район Рудаки, ГРРП',           'drs'],
            ['huroson',    ['tj' => 'Хуросон',    'ru' => 'Хуросон',       'eng' => 'Huroson'],       'Район Хуросон, ГРРП',          'drs'],
            ['roshtqala',  ['tj' => 'Роштқалъа',  'ru' => 'Роштала',       'eng' => 'Roshtqala'],     'Район Роштала, ГРРП',          'drs'],
            ['vahdat_d',   ['tj' => 'Ваҳдат',     'ru' => 'Вахдат',        'eng' => 'Vahdat'],        'Район Вахдат, ГРРП',           'drs'],
            ['hisor_d',    ['tj' => 'Ҳисор',      'ru' => 'Гиссар',        'eng' => 'Hisor'],         'Район Гиссар, ГРРП',           'drs'],
            ['tursunzoda_d', ['tj' => 'Турсунзода', 'ru' => 'Турсунзаде', 'eng' => 'Tursunzoda'],    'Район Турсунзаде, ГРРП',       'drs'],
            ['nurobod',    ['tj' => 'Нуробод',    'ru' => 'Нурабад',       'eng' => 'Nurobod'],       'Район Нурабад, ГРРП',          'drs'],
            ['varzob',     ['tj' => 'Варзоб',     'ru' => 'Варзоб',        'eng' => 'Varzob'],        'Район Варзоб, ГРРП',           'drs'],
            // ── Согдийская область ──
            ['spitamen',   ['tj' => 'Спитамен',   'ru' => 'Спитамен',      'eng' => 'Spitamen'],      'Район Спитамен, Согдийская обл.', 'sughd'],
            ['mastchoh',   ['tj' => 'Мастчоҳ',    'ru' => 'Мастчох',       'eng' => 'Mastchoh'],      'Район Мастчох, Согдийская обл.', 'sughd'],
            ['asht',       ['tj' => 'Ашт',        'ru' => 'Ашт',           'eng' => 'Asht'],          'Район Ашт, Согдийская обл.',   'sughd'],
            ['isfara_d',   ['tj' => 'Исфара',     'ru' => 'Исфара',        'eng' => 'Isfara'],        'Район Исфара, Согдийская обл.', 'sughd'],
            ['ghafurov',   ['tj' => 'Бобоҷон Ғафуров', 'ru' => 'Бободжон Гафуров', 'eng' => 'Bobojon Ghafurov'], 'Район Бободжон Гафуров, Согдийская обл.', 'sughd'],
            ['zafarobod',  ['tj' => 'Зафаробод',  'ru' => 'Зафарабад',     'eng' => 'Zafarobod'],     'Район Зафарабад, Согдийская обл.', 'sughd'],
            // ── Хатлонская область ──
            ['vakhsh_d',   ['tj' => 'Вахш',       'ru' => 'Вахш',          'eng' => 'Vakhsh'],        'Район Вахш, Хатлон',           'hatlon'],
            ['kulob_d',    ['tj' => 'Кӯлоб',      'ru' => 'Куляб',         'eng' => 'Kulob'],         'Куляб, Хатлонская обл.',       'hatlon'],
            ['hamadoni',   ['tj' => 'Ҳамадони',   'ru' => 'Хамадони',      'eng' => 'Hamadoni'],      'Район Хамадони, Хатлон',       'hatlon'],
            ['farkhor_d',  ['tj' => 'Фарҳор',     'ru' => 'Фархор',        'eng' => 'Farkhor'],       'Район Фархор, Хатлон',         'hatlon'],
            ['panj',       ['tj' => 'Панҷ',       'ru' => 'Пяндж',         'eng' => 'Panj'],          'Район Пяндж, Хатлон',          'hatlon'],
            ['norak_d',    ['tj' => 'Норак',      'ru' => 'Нурек',         'eng' => 'Norak'],         'Нурек (город респ. подчинения Хатлона)', 'hatlon'],
            ['muminobod',  ['tj' => 'Муъминобод', 'ru' => 'Муминабад',     'eng' => 'Muminobod'],     'Район Муминабад, Хатлон',      'hatlon'],
            // ── ГБАО ──
            ['shugnan',    ['tj' => 'Шуғнон',     'ru' => 'Шугнан',        'eng' => 'Shugnan'],       'Район Шугнан, ГБАО',           'bmap'],
            ['rushan',     ['tj' => 'Рӯшон',      'ru' => 'Рушан',         'eng' => 'Rushan'],        'Район Рушан, ГБАО',            'bmap'],
            ['ishkoshim_d',['tj' => 'Ишкошим',    'ru' => 'Ишкашим',       'eng' => 'Ishkoshim'],     'Район Ишкашим, ГБАО',          'bmap'],
            ['vanj',       ['tj' => 'Ванҷ',       'ru' => 'Ванч',          'eng' => 'Vanj'],          'Район Ванч, ГБАО',             'bmap'],
            ['darvoz',     ['tj' => 'Дарвоз',     'ru' => 'Дарваз',        'eng' => 'Darvoz'],        'Район Дарваз, ГБАО',           'bmap'],
        ];

        foreach ($districtsData as [$ref, $translations, $desc, $provinceRef]) {
            $district = new District();
            $district->setDescription($desc);

            foreach ($translations as $locale => $title) {
                $district->addTranslation(
                    (new Translation())->setTitle($title)->setLocale($locale)->setAddress($district)
                );
            }

            /** @var Province $province */
            $province = $this->getReference($provinceRef, Province::class);
            $province->addDistrict($district);

            $manager->persist($district);
            $this->addReference($ref, $district);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ProvinceFixture::class];
    }
}

