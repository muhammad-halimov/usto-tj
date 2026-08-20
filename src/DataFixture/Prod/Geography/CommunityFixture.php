<?php

namespace App\DataFixture\Prod\Geography;

use App\Entity\Extra\Translation;
use App\Entity\Geography\District\Community;
use App\Entity\Geography\District\District;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Communities (джамоаты / кварталы) within districts.
 */
class CommunityFixture extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['prod'];
    }

    public function load(ObjectManager $manager): void
    {
        // [$ref, $districtRef, $translations, $desc]
        $communitiesData = [
            // ── Душанбе ──
            ['comm_sino_zarnisor',   'sino',       ['tj' => 'Зарнисор',    'ru' => 'Зарнисор',    'eng' => 'Zarnisor'],   'Джамоати Зарнисор, район Сино, Душанбе'],
            ['comm_sino_bahor',      'sino',       ['tj' => 'Баҳор',       'ru' => 'Бахор',       'eng' => 'Bahor'],      'Джамоати Бахор, район Сино, Душанбе'],
            ['comm_shev_nav',        'schevchenko',['tj' => 'Навобод',     'ru' => 'Навобод',     'eng' => 'Navobod'],    'Джамоати Навобод, район Шевченко, Душанбе'],
            // ── ГРРП ──
            ['comm_rudaki_rohati',   'rudaki',     ['tj' => 'Роҳатӣ',     'ru' => 'Рохати',      'eng' => 'Rokhati'],    'Джамоати Рохати, район Рудаки'],
            ['comm_rudaki_sarband',  'rudaki',     ['tj' => 'Сарбанд',    'ru' => 'Сарбанд',     'eng' => 'Sarband'],    'Джамоати Сарбанд, район Рудаки'],
            ['comm_huroson_kofiron', 'huroson',    ['tj' => 'Кофирниҳон', 'ru' => 'Кофирниган',  'eng' => 'Kofirnigan'], 'Джамоати Кофирниган, район Хуросон'],
            // ── Согдийская ──
            ['comm_spitamen_shirin', 'spitamen',   ['tj' => 'Ширин',      'ru' => 'Ширин',       'eng' => 'Shirin'],     'Джамоати Ширин, район Спитамен'],
            ['comm_mastchoh_zafari', 'mastchoh',   ['tj' => 'Зафарӣ',    'ru' => 'Зафари',      'eng' => 'Zafari'],     'Джамоати Зафари, район Мастчох'],
            // ── Хатлон ──
            ['comm_vakhsh_dusti',    'vakhsh_d',   ['tj' => 'Дӯстӣ',     'ru' => 'Дусти',       'eng' => 'Dusti'],      'Джамоати Дусти, район Вахш'],
            ['comm_vakhsh_nav',      'vakhsh_d',   ['tj' => 'Нав',        'ru' => 'Нов',         'eng' => 'Nov'],        'Джамоати Нов, район Вахш'],
            ['comm_kulob_abdulloev', 'kulob_d',    ['tj' => 'Абдуллоев', 'ru' => 'Абдуллоев',   'eng' => 'Abdulloev'],  'Джамоати Абдуллоев, Куляб'],
            // ── ГБАО ──
            ['comm_shugnan_main',    'shugnan',    ['tj' => 'Шуғнон',     'ru' => 'Шугнан',      'eng' => 'Shugnan'],    'Джамоати Шугнан, район Шугнан, ГБАО'],
            ['comm_rushan_porshnev', 'rushan',     ['tj' => 'Поршнев',   'ru' => 'Поршнев',     'eng' => 'Porshnev'],   'Джамоати Поршнев, район Рушан, ГБАО'],
            ['comm_vanj_yamg',       'vanj',       ['tj' => 'Ямг',        'ru' => 'Ямг',         'eng' => 'Yamg'],       'Джамоати Ямг, район Ванч, ГБАО'],
            // ── ГРРП (новые районы) ──
            ['comm_hisor_gulistonobod', 'hisor_d', ['tj' => 'Гулистонобод', 'ru' => 'Гулистанабад', 'eng' => 'Gulistonobod'], 'Джамоати Гулистанабад, район Гиссар'],
            ['comm_tursunzoda_karatag', 'tursunzoda_d', ['tj' => 'Қаратоғ', 'ru' => 'Каратаг',   'eng' => 'Karatag'],    'Джамоати Каратаг, район Турсунзаде'],
            ['comm_vahdat_gulshan',  'vahdat_d',   ['tj' => 'Гулшан',     'ru' => 'Гулшан',      'eng' => 'Gulshan'],    'Джамоати Гулшан, район Вахдат'],
            // ── Согдийская область (новые районы) ──
            ['comm_isfara_chorku',   'isfara_d',   ['tj' => 'Чоркӯҳ',    'ru' => 'Чоркух',       'eng' => 'Chorku'],     'Джамоати Чоркух, район Исфара'],
            ['comm_ghafurov_oim',    'ghafurov',   ['tj' => 'Ойим',       'ru' => 'Ойим',         'eng' => 'Oyim'],       'Джамоати Ойим, район Б. Гафурова'],
            // ── Хатлонская область (новые районы) ──
            ['comm_farkhor_sarikhosor', 'farkhor_d', ['tj' => 'Сариҳосор', 'ru' => 'Сарихосор',  'eng' => 'Sarikhosor'], 'Джамоати Сарихосор, район Фархор'],
            ['comm_panj_darqad',     'panj',       ['tj' => 'Дарқад',     'ru' => 'Даркад',       'eng' => 'Darqad'],     'Джамоати Даркад, район Пяндж'],
        ];

        foreach ($communitiesData as [$ref, $distRef, $translations, $desc]) {
            $community = new Community();
            $community->setDescription($desc);

            foreach ($translations as $locale => $title) {
                $community->addTranslation(
                    (new Translation())->setTitle($title)->setLocale($locale)->setAddress($community)
                );
            }

            /** @var District $district */
            $district = $this->getReference($distRef, District::class);
            $district->addCommunity($community);

            $manager->persist($community);
            $this->addReference($ref, $community);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [DistrictFixture::class];
    }
}
