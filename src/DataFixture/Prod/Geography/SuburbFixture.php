<?php

namespace App\DataFixture\Prod\Geography;

use App\Entity\Extra\Translation;
use App\Entity\Geography\City\City;
use App\Entity\Geography\City\Suburb;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Suburbs (ПГТ — посёлки городского типа / кварталы городских районов).
 */
class SuburbFixture extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['prod'];
    }

    public function load(ObjectManager $manager): void
    {
        // [$ref, $cityRef, $translations, $desc]
        $suburbsData = [
            // ── Душанбе ──
            ['dshanbe_shohmansur', 'dushanbe', ['tj' => 'Шоҳмансур',        'ru' => 'Шохмансур',           'eng' => 'Shohmansur'],       'ПГТ Шохмансур, Душанбе'],
            ['dshanbe_zarnisor',   'dushanbe', ['tj' => 'Зарнисор',          'ru' => 'Зарнисор',            'eng' => 'Zarnisor'],         'ПГТ Зарнисор, Душанбе'],
            ['dshanbe_ismoil',     'dushanbe', ['tj' => 'Исмоили Сомонӣ',   'ru' => 'Исмоил Сомони',      'eng' => 'Ismoil Somoni'],    'Квартал Исмоил Сомони, Душанбе'],
            ['dshanbe_sino',       'dushanbe', ['tj' => 'Микрорайони Сино',  'ru' => 'Микрорайон Сино',    'eng' => 'Sino Microdistrict'],'Микрорайон Сино, Душанбе'],
            ['dshanbe_behzod',     'dushanbe', ['tj' => 'Беҳзод',            'ru' => 'Бехзод',              'eng' => 'Behzod'],           'Квартал Бехзод, Душанбе'],
            ['dshanbe_shah',       'dushanbe', ['tj' => 'Шаҳринав',          'ru' => 'Шахринав',            'eng' => 'Shahrinav'],        'ПГТ Шахринав, Душанбе'],
            // ── Ходжент ──
            ['hujand_khoja',       'hujand',   ['tj' => 'Хоҷа Аъло',         'ru' => 'Ходжа Ало',           'eng' => 'Khoja Alo'],        'Квартал Ходжа Ало, Ходжент'],
            ['hujand_bogh',        'hujand',   ['tj' => 'Боғи Ширин',        'ru' => 'Баги Ширин',          'eng' => 'Bogi Shirin'],      'Квартал Баги Ширин, Ходжент'],
            ['hujand_kkh',         'hujand',   ['tj' => 'Кӯчаи Хушёр',      'ru' => 'Улица Хушьёр',        'eng' => 'Khushyor St. Area'],'Квартал Хушьёр, Ходжент'],
            // ── Бохтар ──
            ['bohtar_markaz',      'bohtar',   ['tj' => 'Марказ',             'ru' => 'Центральный',         'eng' => 'Center'],           'Центральный квартал, Бохтар'],
            ['bohtar_nav',         'bohtar',   ['tj' => 'Нав',               'ru' => 'Новый',               'eng' => 'New District'],     'Новый квартал, Бохтар'],
            // ── Хорог ──
            ['khorog_markaz',      'khorog',   ['tj' => 'Марказ',             'ru' => 'Центр',               'eng' => 'Center'],           'Центр города, Хорог'],
            ['khorog_porshev',     'khorog',   ['tj' => 'Поршнев',           'ru' => 'Поршнев',             'eng' => 'Porshnev'],         'Квартал Поршнев, Хорог'],
            // ── Исфара ──
            ['isfara_markaz',      'isfara',   ['tj' => 'Марказ',             'ru' => 'Центральный',         'eng' => 'Center'],           'Центральный квартал, Исфара'],
            ['isfara_chorku_q',    'isfara',   ['tj' => 'Чоркӯҳӣ',          'ru' => 'Чоркухский',          'eng' => 'Chorku Area'],      'Квартал Чоркухский, Исфара'],
            // ── Гиссар ──
            ['hisor_markaz',       'hisor',    ['tj' => 'Марказ',             'ru' => 'Центр',               'eng' => 'Center'],           'Центр города, Гиссар'],
            // ── Турсунзаде ──
            ['tursunzoda_markaz',  'tursunzoda',['tj' => 'Марказ',            'ru' => 'Центр',               'eng' => 'Center'],           'Центр города, Турсунзаде'],
            // ── Нурек ──
            ['norak_markaz',       'norak',    ['tj' => 'Марказ',             'ru' => 'Центр',               'eng' => 'Center'],           'Центр города, Нурек'],
        ];

        foreach ($suburbsData as [$ref, $cityRef, $translations, $desc]) {
            $suburb = new Suburb();
            $suburb->setDescription($desc);

            foreach ($translations as $locale => $title) {
                $suburb->addTranslation(
                    (new Translation())->setTitle($title)->setLocale($locale)->setAddress($suburb)
                );
            }

            /** @var City $city */
            $city = $this->getReference($cityRef, City::class);
            $city->addSuburb($suburb);

            $manager->persist($suburb);
            $this->addReference($ref, $suburb);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CityFixture::class];
    }
}
