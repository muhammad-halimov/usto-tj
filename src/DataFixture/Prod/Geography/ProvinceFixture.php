<?php

namespace App\DataFixture\Prod\Geography;

use App\Entity\Extra\Translation;
use App\Entity\Geography\City\City;
use App\Entity\Geography\Province\Province;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use ReflectionClass;

class ProvinceFixture extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['prod'];
    }

    public function load(ObjectManager $manager): void
    {
        $provincesData = [
            ['drs',       ['tj' => 'НТМ',               'ru' => 'РРП',                 'eng' => 'DRS'],             'РРП, Районы республиканского подчинения, западный Таджикистан',           ['vahdat', 'rogun', 'faizobod']],
            ['sughd',     ['tj' => 'Вилояти Суғд',      'ru' => 'Согдийская область',  'eng' => 'Sughd Province'],  'Согдийская область, северный Таджикистан',                                ['hujand', 'istaravshan', 'konibodom', 'panjakent', 'buston']],
            ['hatlon',    ['tj' => 'Вилояти Хатлон',    'ru' => 'Хатлонская область',  'eng' => 'Hatlon Province'], 'Хатлонская область, южный Таджикистан',                                   ['bohtar', 'kulob', 'qurghonteppa', 'vose', 'danghara', 'vakhsh']],
            ['bmap',      ['tj' => 'ВМКБ',              'ru' => 'ГБАО',                'eng' => 'BMAP'],            'ГБАО, Горно-Бадахшанская Автономная область, восточный Таджикистан',      ['murghob', 'khorog', 'ishkoshim']],
            ['dushanbe',  ['tj' => 'Душанбе',           'ru' => 'Душанбе',             'eng' => 'Dushanbe'],        'Душанбе, республиканская столица, западный Таджикистан',                  ['dushanbe']],
        ];

        foreach ($provincesData as [$ref, $translations, $desc, $citiesRefs]) {
            $province = new Province();
            $province->setDescription($desc);

            $reflection = new ReflectionClass($province);
            /** @noinspection PhpStatementHasEmptyBodyInspection */
            while (!$reflection->hasProperty('translations') && $reflection = $reflection->getParentClass());
            $property = $reflection->getProperty('translations');
            $property->setValue($province, new ArrayCollection());

            foreach ($translations as $locale => $title) {
                $translation = (new Translation())
                    ->setTitle($title)
                    ->setLocale($locale)
                    ->setAddress($province);

                $province->addTranslation($translation);
            }

            foreach ($citiesRefs as $cityRef) {
                $province->addCity($this->getReference($cityRef, City::class));
            }

            $manager->persist($province);
            $this->addReference($ref, $province);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CityFixture::class];
    }
}
