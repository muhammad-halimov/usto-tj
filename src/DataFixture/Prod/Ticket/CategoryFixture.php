<?php

namespace App\DataFixture\Prod\Ticket;

use App\Entity\Extra\Translation;
use App\Entity\Ticket\Category;
use App\Entity\User\Occupation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use ReflectionClass;

/**
 * ЧИСТО прод-данные — не зависит ни от чего из App\DataFixture\Dev, чтобы
 * `doctrine:fixtures:load --group=prod` реально грузил только это, без
 * dev-тикетов/пользователей. Привязка dev-тикетов к конкретным подкатегориям
 * (раньше жила прямо тут, под ключом 'tickets') переехала в отдельную
 * dev-фикстуру — см. App\DataFixture\Dev\Additional\TicketCategoryLinkFixture.
 */
class CategoryFixture extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['prod'];
    }

    public function load(ObjectManager $manager): void
    {
        // occupations: ВСЕ подкатегории этой категории (one-to-many,
        // Category::addOccupation() → Occupation::setCategory()) — раньше
        // привязывалась только одна "основная" профессия. Теперь — полный список.
        $categoriesData = [
            'santexnika' => [
                'translations' => ['tj' => 'Сантехника',              'ru' => 'Сантехника',                    'eng' => 'Plumbing'],
                'description'  => "Кори сантехникӣ\nСантехнические работы\nPlumbing works",
                'occupations'  => ['santexnik', 'truboprovodchik', 'svarshik', 'montazhnik_otopleniya'],
            ],
            'it' => [
                'translations' => ['tj' => 'ТИ',                      'ru' => 'IT',                            'eng' => 'IT'],
                'description'  => "Барномасозӣ, амнияти кибернетикӣ, devops\nПрограммирование, кибербезопасность, devops\nProgramming, cybersecurity, devops",
                'occupations'  => ['programmer', 'sysadmin', 'network_engineer', 'mobile_developer', 'qa_engineer', 'data_analyst'],
            ],
            'beauty' => [
                'translations' => ['tj' => 'Зебоӣ ва саломатӣ',       'ru' => 'Красота и здоровье',            'eng' => 'Beauty and Health'],
                'description'  => "Хизматҳои зебоӣ ва саломатӣ\nУслуги красоты и здоровья\nBeauty and health services",
                'occupations'  => ['kosmetolog', 'masseur', 'parikmakher', 'manikyurshitsa', 'vizazhist', 'brovist'],
            ],
            'repair' => [
                'translations' => ['tj' => 'Таъмири хона',             'ru' => 'Ремонт и строительство',       'eng' => 'Repair and Construction'],
                'description'  => "Таъмири хона ва иншоот\nРемонт и строительство\nHome repair and construction",
                'occupations'  => ['stroitel', 'plitochnik', 'maljar', 'metalist', 'shtukatur', 'krovelshik', 'okonshik'],
            ],
            'electricity' => [
                'translations' => ['tj' => 'Барқкашӣ',                 'ru' => 'Электрика',                    'eng' => 'Electrical'],
                'description'  => "Кори барқкашӣ\nЭлектромонтажные работы\nElectrical installation works",
                'occupations'  => ['elektrik', 'energetik', 'liftyor'],
            ],
            'cleaning' => [
                'translations' => ['tj' => 'Тозакунӣ',                 'ru' => 'Уборка',                       'eng' => 'Cleaning'],
                'description'  => "Хизматҳои тозакунӣ\nУслуги уборки\nCleaning services",
                'occupations'  => ['kliner', 'himchistka', 'moyshik_okon', 'domrabotnitsa'],
            ],
            'transport' => [
                'translations' => ['tj' => 'Нақлиёт',                  'ru' => 'Транспорт и перевозки',        'eng' => 'Transport and Logistics'],
                'description'  => "Нақлиёт ва боркашонӣ\nТранспорт и грузоперевозки\nTransport and cargo services",
                'occupations'  => ['voditel', 'gruzchik', 'kurier', 'ekspeditor', 'taksist'],
            ],
            'education' => [
                'translations' => ['tj' => 'Таълим',                   'ru' => 'Образование и репетиторство',  'eng' => 'Education and Tutoring'],
                'description'  => "Таълим ва омӯзгорӣ\nОбразование и репетиторство\nEducation and tutoring",
                'occupations'  => ['repetitor', 'language_trainer', 'muzykalny_pedagog', 'logoped', 'trener_shakhmat'],
            ],
            'auto' => [
                'translations' => ['tj' => 'Таъмири автомобил',        'ru' => 'Авто и автосервис',            'eng' => 'Auto and Car Service'],
                'description'  => "Таъмир ва хизматрасонии автомобил\nАвтосервис и ремонт\nAuto repair and service",
                'occupations'  => ['avtomehanik', 'avtoelektrik', 'shinomontazhnik', 'avtomoyshik'],
            ],
            'design' => [
                'translations' => ['tj' => 'Дизайн',                   'ru' => 'Дизайн и творчество',          'eng' => 'Design and Creative'],
                'description'  => "Дизайни графикӣ ва эҷодӣ\nГрафический и творческий дизайн\nGraphic and creative design",
                'occupations'  => ['grafik_dizayner', 'veb_dizayner', 'interior_dizayner', 'illustrator', 'modelyer'],
            ],
            'legal' => [
                'translations' => ['tj' => 'Хизматҳои ҳуқуқӣ',        'ru' => 'Юридические услуги',           'eng' => 'Legal Services'],
                'description'  => "Машваратҳои ҳуқуқӣ\nЮридические консультации\nLegal consultations",
                'occupations'  => ['yurist', 'advokat', 'nalogovy_konsultant'],
            ],
            'accounting' => [
                'translations' => ['tj' => 'Бухгалтерия',              'ru' => 'Бухгалтерия и финансы',        'eng' => 'Accounting and Finance'],
                'description'  => "Бухгалтерӣ ва молия\nБухгалтерия и финансы\nAccounting and finance",
                'occupations'  => ['buhgalter', 'auditor', 'kadrovik'],
            ],
            'photography' => [
                'translations' => ['tj' => 'Аксбардорӣ',               'ru' => 'Фото и видеосъёмка',           'eng' => 'Photography and Videography'],
                'description'  => "Аксбардорӣ ва видеогирӣ\nФото и видеосъёмка\nPhotography and videography",
                'occupations'  => ['fotograf', 'videograf', 'montazher_video', 'retusher'],
            ],
            'medicine' => [
                'translations' => ['tj' => 'Тиб ва саломатӣ',          'ru' => 'Медицина и здоровье',          'eng' => 'Medicine and Healthcare'],
                'description'  => "Хизматҳои тиббӣ\nМедицинские услуги\nMedical services",
                'occupations'  => ['vrach', 'medsestra', 'stomatolog', 'farmatsevt'],
            ],
            'fitness' => [
                'translations' => ['tj' => 'Варзиш ва фитнес',         'ru' => 'Спорт и фитнес',               'eng' => 'Sports and Fitness'],
                'description'  => "Варзиш ва фитнес\nСпорт и фитнес\nSports and fitness",
                'occupations'  => ['personal_trainer', 'yoga_instruktor', 'trener_plavaniya', 'dietolog'],
            ],
            'events' => [
                'translations' => ['tj' => 'Чорабиниҳо',               'ru' => 'Мероприятия и ивент',          'eng' => 'Events and Entertainment'],
                'description'  => "Ташкили чорабиниҳо\nОрганизация мероприятий\nEvent planning and entertainment",
                'occupations'  => ['event_manager', 'toastmaster', 'dj', 'dekorator', 'animator'],
            ],
            'security' => [
                'translations' => ['tj' => 'Амнияти объект',           'ru' => 'Охрана и безопасность',        'eng' => 'Security Services'],
                'description'  => "Хизматҳои амнияти\nОхрана и безопасность\nSecurity and guarding services",
                'occupations'  => ['ohrannik', 'telohranitel', 'montazhnik_signalizacii', 'master_zamkov'],
            ],
            'animals' => [
                'translations' => ['tj' => 'Нигоҳубини ҳайвонот',      'ru' => 'Уход за животными',            'eng' => 'Pet Care'],
                'description'  => "Нигоҳубини ҳайвонот\nУход за домашними животными\nPet care and grooming",
                'occupations'  => ['veterinar', 'groomer', 'dog_trainer', 'petsitter'],
            ],

            // ── Новые категории ──────────────────────────────────────────
            'appliance_repair' => [
                'translations' => ['tj' => 'Таъмири техникаи рӯзгор',  'ru' => 'Ремонт бытовой техники',       'eng' => 'Appliance Repair'],
                'description'  => "Таъмири яхдон, мошини либосшӯӣ, кондитсионер ва дигар техникаи рӯзгор\nРемонт холодильников, стиральных машин, кондиционеров и другой бытовой техники\nRepair of fridges, washing machines, air conditioners and other home appliances",
                'occupations'  => ['master_holodilnikov', 'master_stiralnyh_mashin', 'master_konditsionerov', 'master_televizorov'],
            ],
            'furniture' => [
                'translations' => ['tj' => 'Мебел',                    'ru' => 'Мебель',                       'eng' => 'Furniture'],
                'description'  => "Ҷамъоварӣ, таъмир ва сохтани мебели фармоишӣ\nСборка, ремонт и изготовление мебели на заказ\nFurniture assembly, repair and custom-made pieces",
                'occupations'  => ['sborshik_mebeli', 'obivshik_mebeli', 'stolyar', 'mebelshik_na_zakaz'],
            ],
            'childcare' => [
                'translations' => ['tj' => 'Нигоҳубини кӯдакон',       'ru' => 'Няни и уход за детьми',        'eng' => 'Childcare'],
                'description'  => "Нигоҳубин, тарбия ва бозигарии кӯдакон\nПрисмотр, воспитание и досуг детей\nChildminding, upbringing and children's entertainment",
                'occupations'  => ['nyanya', 'guvernantka', 'detsky_animator'],
            ],
            'sewing' => [
                'translations' => ['tj' => 'Хизматҳои дӯзандагӣ',      'ru' => 'Швейные услуги',               'eng' => 'Tailoring and Sewing'],
                'description'  => "Дӯхтан, ислоҳ ва гулдӯзии либос\nПошив, ремонт и вышивка одежды\nClothing sewing, alteration and embroidery",
                'occupations'  => ['shvea', 'zakroyshik', 'vyshivalshitsa'],
            ],
            'translation' => [
                'translations' => ['tj' => 'Хизматҳои тарҷумонӣ',      'ru' => 'Переводческие услуги',         'eng' => 'Translation Services'],
                'description'  => "Тарҷумаи шифоҳӣ ва хаттӣ ба забонҳои гуногун\nУстный и письменный перевод на разные языки\nInterpreting and translation into various languages",
                'occupations'  => ['perevodchik_ustny', 'perevodchik_pismenny', 'gid_perevodchik'],
            ],
            'catering' => [
                'translations' => ['tj' => 'Ошпазӣ ва кейтеринг',      'ru' => 'Кулинария и кейтеринг',        'eng' => 'Cooking and Catering'],
                'description'  => "Пухтупаз, қаннодӣ ва хизматрасонии дастархон барои чорабиниҳо\nГотовка, кондитерские изделия и обслуживание стола на мероприятиях\nCooking, baking and table service for events",
                'occupations'  => ['povar', 'konditer', 'keytering_specialist', 'barista'],
            ],
            'realestate' => [
                'translations' => ['tj' => 'Амволи ғайриманқул',       'ru' => 'Недвижимость',                 'eng' => 'Real Estate'],
                'description'  => "Хариду фурӯш, иҷора ва идораи амволи ғайриманқул\nПокупка, продажа, аренда и управление недвижимостью\nBuying, selling, renting and managing property",
                'occupations'  => ['rieltor', 'otsenshik_nedvizhimosti', 'upravlyayuschy_nedvizhimostyu'],
            ],
            'landscaping' => [
                'translations' => ['tj' => 'Боғдорӣ ва ландшафт',      'ru' => 'Ландшафт и сад',               'eng' => 'Landscaping and Gardening'],
                'description'  => "Нигоҳубини боғ ва лоиҳакашии ҳудуди берунӣ\nУход за садом и проектирование благоустройства территории\nGarden care and outdoor landscape design",
                'occupations'  => ['sadovnik', 'landshaftny_dizayner', 'agronom'],
            ],
            'device_repair' => [
                'translations' => ['tj' => 'Таъмири компютер ва телефон', 'ru' => 'Ремонт компьютеров и телефонов', 'eng' => 'Computer and Phone Repair'],
                'description'  => "Таъмири компютер, телефон, планшет ва принтер\nРемонт компьютеров, телефонов, планшетов и принтеров\nComputer, phone, tablet and printer repair",
                'occupations'  => ['master_computerov', 'master_telefonov', 'master_planshetov', 'master_printerov'],
            ],
            'handyman' => [
                'translations' => ['tj' => 'Хизматҳои ёрирасон',        'ru' => 'Разнорабочие услуги',          'eng' => 'Handyman Services'],
                'description'  => "Кӯмаки жисмонӣ ва ислоҳи хурди хона\nФизическая помощь и мелкий бытовой ремонт\nPhysical help and small household fixes",
                'occupations'  => ['raznorabochiy', 'podsobnik', 'master_na_chas'],
            ],
            'jewelry_watches' => [
                'translations' => ['tj' => 'Заргарӣ ва соатсозӣ',       'ru' => 'Ювелирные и часовые услуги',   'eng' => 'Jewellery and Watch Services'],
                'description'  => "Таъмир ва сохтани заргарӣ ва соат\nРемонт и изготовление ювелирных изделий и часов\nJewellery and watch repair and crafting",
                'occupations'  => ['yuvelir', 'chasovshik', 'graver'],
            ],
            'psychology' => [
                'translations' => ['tj' => 'Равоншиносӣ ва машварат',   'ru' => 'Психология и консультации',    'eng' => 'Psychology and Counselling'],
                'description'  => "Машваратҳои равоншиносӣ ва оилавӣ\nПсихологические и семейные консультации\nPsychological and family counselling",
                'occupations'  => ['psycholog', 'semeynyy_konsultant', 'coach'],
            ],
            'printing' => [
                'translations' => ['tj' => 'Чоп ва полиграфия',         'ru' => 'Печать и полиграфия',          'eng' => 'Printing Services'],
                'description'  => "Чопи маводи рекламавӣ ва тарҳрезии он\nПечать рекламной продукции и её дизайн\nPrinting of promo materials and their design",
                'occupations'  => ['tipograf', 'dizayner_pechati'],
            ],
            'shoe_leather' => [
                'translations' => ['tj' => 'Пойафзол ва маснуоти чармӣ', 'ru' => 'Обувь и изделия из кожи',     'eng' => 'Shoe and Leather Repair'],
                'description'  => "Таъмири пойафзол ва маснуоти чармӣ\nРемонт обуви и изделий из кожи\nShoe and leather goods repair",
                'occupations'  => ['sapozhnik', 'kozhevnik'],
            ],
        ];

        foreach ($categoriesData as $key => $data) {
            $category = new Category();
            $category->setDescription($data['description']);

            $reflection = new ReflectionClass($category);
            /** @noinspection PhpStatementHasEmptyBodyInspection */
            while (!$reflection->hasProperty('translations') && $reflection = $reflection->getParentClass());
            $property = $reflection->getProperty('translations');
            $property->setValue($category, new ArrayCollection());

            foreach ($data['translations'] as $locale => $title) {
                $translation = (new Translation())
                    ->setTitle($title)
                    ->setLocale($locale)
                    ->setCategory($category);

                $category->addTranslation($translation);
            }

            // Category ↔ Occupation, многие-ко-многим — ВСЕ подкатегории,
            // не только одна "основная" (см. докблок $categoriesData выше).
            foreach ($data['occupations'] as $occupationRef) {
                $category->addOccupation($this->getReference($occupationRef, Occupation::class));
            }

            $manager->persist($category);
            $this->addReference($key, $category);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            OccupationFixture::class,
        ];
    }
}
