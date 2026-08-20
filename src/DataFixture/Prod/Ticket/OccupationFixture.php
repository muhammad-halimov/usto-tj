<?php

namespace App\DataFixture\Prod\Ticket;

use App\Entity\Extra\Translation;
use App\Entity\User\Occupation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ObjectManager;
use ReflectionClass;

/**
 * Подкатегории (Occupation) — многие ссылаются по ref-ключу из MasterFixture
 * (мастер → своя специальность) и CategoryFixture (категория → её
 * подкатегории, one-to-many через Category::addOccupation() →
 * Occupation::setCategory() — каждая подкатегория принадлежит ровно одной
 * категории).
 *
 * Ref-ключи существующих записей — НЕ переименовывать: они завязаны как
 * минимум на MasterFixture (см. там 'programmer', 'santexnik', 'stroitel',
 * 'voditel', 'elektrik', 'kliner', 'masseur', 'yurist', 'avtomehanik',
 * 'parikmakher', 'fotograf', 'videograf', 'kosmetolog', 'repetitor',
 * 'grafik_dizayner') и на CategoryFixture (тикеты и occupations[] там же).
 * Новые записи ниже дописаны рядом, сгруппированы по категориям для
 * читаемости (реальная привязка к категории — в CategoryFixture::occupations).
 */
class OccupationFixture extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['prod'];
    }

    public function load(ObjectManager $manager): void
    {
        $occupationsData = [
            // ── Сантехника ──────────────────────────────────────────────
            'santexnik' => [
                'translations' => ['tj' => 'Сантехник', 'ru' => 'Сантехник', 'eng' => 'Plumber'],
                'description'  => "Кори сантехникӣ\nСантехнические работы\nPlumbing works",
            ],
            'truboprovodchik' => [
                'translations' => ['tj' => 'Қубурсоз', 'ru' => 'Трубопроводчик', 'eng' => 'Pipefitter'],
                'description'  => "Насби қубурҳо\nМонтаж трубопроводов\nPipe installation",
            ],
            'svarshik' => [
                'translations' => ['tj' => 'Пайвандгар', 'ru' => 'Сварщик', 'eng' => 'Welder'],
                'description'  => "Пайвандкории металл ва қубурҳо\nСварка металла и труб\nMetal and pipe welding",
            ],
            'montazhnik_otopleniya' => [
                'translations' => ['tj' => 'Насбкунандаи гармидиҳӣ', 'ru' => 'Монтажник отопления', 'eng' => 'Heating Systems Installer'],
                'description'  => "Насб ва хизматрасонии системаи гармидиҳӣ\nМонтаж и обслуживание систем отопления\nHeating system installation and service",
            ],

            // ── IT ───────────────────────────────────────────────────────
            'programmer' => [
                'translations' => ['tj' => 'Барномасоз', 'ru' => 'Программист', 'eng' => 'Programmer'],
                'description'  => "Барномасозӣ\nПрограммирование\nProgramming",
            ],
            'sysadmin' => [
                'translations' => ['tj' => 'Маъмури система', 'ru' => 'Системный администратор', 'eng' => 'System Administrator'],
                'description'  => "Идоракунии системаҳо\nАдминистрирование систем\nSystems administration",
            ],
            'network_engineer' => [
                'translations' => ['tj' => 'Муҳандиси шабака', 'ru' => 'Сетевой инженер', 'eng' => 'Network Engineer'],
                'description'  => "Шабакаҳои компютерӣ\nКомпьютерные сети\nComputer networking",
            ],
            'mobile_developer' => [
                'translations' => ['tj' => 'Барномасози мобилӣ', 'ru' => 'Разработчик мобильных приложений', 'eng' => 'Mobile App Developer'],
                'description'  => "Таҳияи барномаҳои мобилӣ барои iOS ва Android\nРазработка мобильных приложений для iOS и Android\niOS and Android mobile app development",
            ],
            'qa_engineer' => [
                'translations' => ['tj' => 'Санҷишгари сифат', 'ru' => 'Тестировщик (QA)', 'eng' => 'QA Engineer'],
                'description'  => "Санҷиши сифати барномаҳо\nТестирование качества программного обеспечения\nSoftware quality testing",
            ],
            'data_analyst' => [
                'translations' => ['tj' => 'Таҳлилгари маълумот', 'ru' => 'Аналитик данных', 'eng' => 'Data Analyst'],
                'description'  => "Таҳлил ва коркарди маълумот\nАнализ и обработка данных\nData analysis and processing",
            ],

            // ── Красота и здоровье ──────────────────────────────────────
            'parikmakher' => [
                'translations' => ['tj' => 'Сартарош', 'ru' => 'Парикмахер', 'eng' => 'Hairdresser'],
                'description'  => "Сартарошӣ\nПарикмахерские услуги\nHairdressing",
            ],
            'kosmetolog' => [
                'translations' => ['tj' => 'Косметолог', 'ru' => 'Косметолог', 'eng' => 'Cosmetologist'],
                'description'  => "Хизматҳои косметологӣ\nКосметологические услуги\nCosmetology services",
            ],
            'masseur' => [
                'translations' => ['tj' => 'Массажист', 'ru' => 'Массажист', 'eng' => 'Masseur'],
                'description'  => "Массаж\nМассаж\nMassage",
            ],
            'manikyurshitsa' => [
                'translations' => ['tj' => 'Устои маникюр', 'ru' => 'Мастер маникюра', 'eng' => 'Manicurist'],
                'description'  => "Маникюр ва педикюр\nМаникюр и педикюр\nManicure and pedicure",
            ],
            'vizazhist' => [
                'translations' => ['tj' => 'Ороишгар', 'ru' => 'Визажист', 'eng' => 'Makeup Artist'],
                'description'  => "Ороиши рӯй барои чорабиниҳо ва аксбардорӣ\nМакияж для мероприятий и фотосъёмки\nMakeup for events and photoshoots",
            ],
            'brovist' => [
                'translations' => ['tj' => 'Устои абрувон', 'ru' => 'Мастер бровей и ресниц', 'eng' => 'Brow and Lash Specialist'],
                'description'  => "Ороиш ва рангубори абрувону мижгонҳо\nОформление и окрашивание бровей и ресниц\nBrow and lash shaping and tinting",
            ],

            // ── Ремонт и строительство ──────────────────────────────────
            'stroitel' => [
                'translations' => ['tj' => 'Сохтмончӣ', 'ru' => 'Строитель', 'eng' => 'Builder'],
                'description'  => "Кори сохтмонӣ\nСтроительные работы\nConstruction works",
            ],
            'plitochnik' => [
                'translations' => ['tj' => 'Плиточник', 'ru' => 'Плиточник', 'eng' => 'Tiler'],
                'description'  => "Гузоштани кафпӯш\nУкладка плитки\nTile laying",
            ],
            'maljar' => [
                'translations' => ['tj' => 'Наққош', 'ru' => 'Маляр', 'eng' => 'Painter'],
                'description'  => "Рангубор\nМалярные работы\nPainting works",
            ],
            'metalist' => [
                'translations' => ['tj' => 'Слесар', 'ru' => 'Слесарь', 'eng' => 'Metalworker'],
                'description'  => "Кор бо металл ва механизмҳо\nРаботы с металлом и механизмами\nMetal and mechanical works",
            ],
            'shtukatur' => [
                'translations' => ['tj' => 'Сувоккор', 'ru' => 'Штукатур', 'eng' => 'Plasterer'],
                'description'  => "Сувоккорӣ ва ҳамворкунии девор\nШтукатурные и выравнивающие работы\nPlastering and wall levelling",
            ],
            'krovelshik' => [
                'translations' => ['tj' => 'Бомсоз', 'ru' => 'Кровельщик', 'eng' => 'Roofer'],
                'description'  => "Гузоштан ва таъмири бом\nУстройство и ремонт кровли\nRoof installation and repair",
            ],
            'okonshik' => [
                'translations' => ['tj' => 'Устои тирезаҳои ПХВ', 'ru' => 'Мастер по установке окон', 'eng' => 'Window Installer'],
                'description'  => "Насби тирезаву дарҳои ПХВ\nУстановка пластиковых окон и дверей\nPVC window and door installation",
            ],

            // ── Электрика ────────────────────────────────────────────────
            'elektrik' => [
                'translations' => ['tj' => 'Барқкаш', 'ru' => 'Электрик', 'eng' => 'Electrician'],
                'description'  => "Кори барқкашӣ\nЭлектромонтажные работы\nElectrical works",
            ],
            'energetik' => [
                'translations' => ['tj' => 'Муҳандиси барқ', 'ru' => 'Инженер-энергетик', 'eng' => 'Power Systems Engineer'],
                'description'  => "Лоиҳакашӣ ва хизматрасонии таҷҳизоти барқӣ\nПроектирование и обслуживание электрооборудования\nElectrical equipment design and service",
            ],
            'liftyor' => [
                'translations' => ['tj' => 'Устои лифт', 'ru' => 'Специалист по лифтам', 'eng' => 'Elevator Technician'],
                'description'  => "Насб ва таъмири лифт\nМонтаж и ремонт лифтового оборудования\nElevator installation and repair",
            ],

            // ── Уборка ───────────────────────────────────────────────────
            'kliner' => [
                'translations' => ['tj' => 'Тозакунанда', 'ru' => 'Клинер', 'eng' => 'Cleaner'],
                'description'  => "Тозакунии хона\nУборка помещений\nCleaning services",
            ],
            'himchistka' => [
                'translations' => ['tj' => 'Устои хушкшӯӣ', 'ru' => 'Мастер химчистки', 'eng' => 'Dry Cleaning Specialist'],
                'description'  => "Хушкшӯии мебел ва гилем\nХимчистка мебели и ковров\nUpholstery and carpet dry cleaning",
            ],
            'moyshik_okon' => [
                'translations' => ['tj' => 'Тозакунандаи тирезаҳо', 'ru' => 'Мойщик окон', 'eng' => 'Window Cleaner'],
                'description'  => "Тозакунии тирезаву фасад\nМытьё окон и фасадов\nWindow and facade cleaning",
            ],
            'domrabotnitsa' => [
                'translations' => ['tj' => 'Хизматгори хона', 'ru' => 'Домработница', 'eng' => 'Housekeeper'],
                'description'  => "Хизматрасонии рӯзонаи хонагӣ\nЕжедневная помощь по хозяйству\nDaily household help",
            ],

            // ── Транспорт ────────────────────────────────────────────────
            'voditel' => [
                'translations' => ['tj' => 'Ронанда', 'ru' => 'Водитель', 'eng' => 'Driver'],
                'description'  => "Хизматрасонии нақлиётӣ\nВодительские услуги\nDriver services",
            ],
            'gruzchik' => [
                'translations' => ['tj' => 'Борбардор', 'ru' => 'Грузчик', 'eng' => 'Loader'],
                'description'  => "Бордошт ва интиқол\nПогрузка и перевозка\nLoading and transport",
            ],
            'kurier' => [
                'translations' => ['tj' => 'Қосид', 'ru' => 'Курьер', 'eng' => 'Courier'],
                'description'  => "Расонидани бастаҳо ва фармоишҳо\nДоставка посылок и заказов\nPackage and order delivery",
            ],
            'ekspeditor' => [
                'translations' => ['tj' => 'Экспедитор', 'ru' => 'Экспедитор', 'eng' => 'Freight Forwarder'],
                'description'  => "Ташкили боркашонии молҳо\nОрганизация грузоперевозок\nCargo transport organisation",
            ],
            'taksist' => [
                'translations' => ['tj' => 'Таксӣ ронанда', 'ru' => 'Таксист', 'eng' => 'Taxi Driver'],
                'description'  => "Интиқоли мусофирон бо нархи мувофиқашуда\nПеревозка пассажиров по договорной цене\nPassenger transport at an agreed price",
            ],

            // ── Образование ──────────────────────────────────────────────
            'repetitor' => [
                'translations' => ['tj' => 'Омӯзгор-роҳбар', 'ru' => 'Репетитор', 'eng' => 'Tutor'],
                'description'  => "Дарсҳои хусусӣ\nЧастные уроки\nPrivate tutoring",
            ],
            'language_trainer' => [
                'translations' => ['tj' => 'Омӯзгори забон', 'ru' => 'Преподаватель языков', 'eng' => 'Language Teacher'],
                'description'  => "Таълими забонҳо\nОбучение языкам\nLanguage teaching",
            ],
            'muzykalny_pedagog' => [
                'translations' => ['tj' => 'Омӯзгори мусиқӣ', 'ru' => 'Педагог по музыке', 'eng' => 'Music Teacher'],
                'description'  => "Таълими навохтани асбобҳои мусиқӣ ва суруд\nОбучение игре на музыкальных инструментах и вокалу\nMusic instrument and vocal lessons",
            ],
            'logoped' => [
                'translations' => ['tj' => 'Логопед', 'ru' => 'Логопед', 'eng' => 'Speech Therapist'],
                'description'  => "Ислоҳи нутқи кӯдакон ва калонсолон\nКоррекция речи у детей и взрослых\nSpeech correction for children and adults",
            ],
            'trener_shakhmat' => [
                'translations' => ['tj' => 'Мураббии шоҳмот', 'ru' => 'Тренер по шахматам', 'eng' => 'Chess Coach'],
                'description'  => "Таълими шоҳмот барои кӯдакон ва калонсолон\nОбучение шахматам для детей и взрослых\nChess lessons for children and adults",
            ],

            // ── Авто ─────────────────────────────────────────────────────
            'avtomehanik' => [
                'translations' => ['tj' => 'Автомеханик', 'ru' => 'Автомеханик', 'eng' => 'Car Mechanic'],
                'description'  => "Таъмири автомобил\nРемонт автомобилей\nCar repair",
            ],
            'avtoelektrik' => [
                'translations' => ['tj' => 'Автобарқкаш', 'ru' => 'Автоэлектрик', 'eng' => 'Auto Electrician'],
                'description'  => "Ташхис ва таъмири барқи автомобил\nДиагностика и ремонт автомобильной электрики\nCar electrical diagnostics and repair",
            ],
            'shinomontazhnik' => [
                'translations' => ['tj' => 'Шиномонтажник', 'ru' => 'Шиномонтажник', 'eng' => 'Tyre Fitter'],
                'description'  => "Иваз ва мувозинати чархҳо\nЗамена и балансировка колёс\nTyre change and wheel balancing",
            ],
            'avtomoyshik' => [
                'translations' => ['tj' => 'Автошӯянда', 'ru' => 'Мойщик автомобилей', 'eng' => 'Car Washer'],
                'description'  => "Шустушӯи автомобил дар хона\nМойка автомобиля с выездом на дом\nMobile car washing service",
            ],

            // ── Дизайн ───────────────────────────────────────────────────
            'grafik_dizayner' => [
                'translations' => ['tj' => 'Дизайнери графикӣ', 'ru' => 'Графический дизайнер', 'eng' => 'Graphic Designer'],
                'description'  => "Дизайни графикӣ\nГрафический дизайн\nGraphic design",
            ],
            'veb_dizayner' => [
                'translations' => ['tj' => 'Дизайнери веб', 'ru' => 'Веб-дизайнер', 'eng' => 'Web Designer'],
                'description'  => "Дизайни веб-сайтҳо\nДизайн веб-сайтов\nWeb design",
            ],
            'interior_dizayner' => [
                'translations' => ['tj' => 'Дизайнери дохилӣ', 'ru' => 'Дизайнер интерьера', 'eng' => 'Interior Designer'],
                'description'  => "Лоиҳакашии дизайни дохилии хона\nПроектирование дизайна интерьера\nInterior design planning",
            ],
            'illustrator' => [
                'translations' => ['tj' => 'Тасвиргар', 'ru' => 'Иллюстратор', 'eng' => 'Illustrator'],
                'description'  => "Расмкашии дигиталӣ ва дастӣ\nЦифровая и ручная иллюстрация\nDigital and hand-drawn illustration",
            ],
            'modelyer' => [
                'translations' => ['tj' => 'Дӯзандаи либос', 'ru' => 'Модельер', 'eng' => 'Fashion Designer'],
                'description'  => "Тарроҳӣ ва дӯхтани либоси фармоишӣ\nПроектирование и пошив одежды на заказ\nCustom clothing design and tailoring",
            ],

            // ── Юридические услуги ───────────────────────────────────────
            'yurist' => [
                'translations' => ['tj' => 'Ҳуқуқшинос', 'ru' => 'Юрист', 'eng' => 'Lawyer'],
                'description'  => "Машваратҳои ҳуқуқӣ\nЮридические консультации\nLegal consultations",
            ],
            'advokat' => [
                'translations' => ['tj' => 'Адвокат', 'ru' => 'Адвокат', 'eng' => 'Advocate'],
                'description'  => "Ҳимоя дар суд\nПредставительство и защита в суде\nCourt representation and defence",
            ],
            'nalogovy_konsultant' => [
                'translations' => ['tj' => 'Мушовири андоз', 'ru' => 'Налоговый консультант', 'eng' => 'Tax Consultant'],
                'description'  => "Машварат оид ба масъалаҳои андоз\nКонсультации по налоговым вопросам\nTax-related consulting",
            ],

            // ── Бухгалтерия ──────────────────────────────────────────────
            'buhgalter' => [
                'translations' => ['tj' => 'Ҳисобдор', 'ru' => 'Бухгалтер', 'eng' => 'Accountant'],
                'description'  => "Ҳисобдорӣ\nБухгалтерия\nAccounting",
            ],
            'auditor' => [
                'translations' => ['tj' => 'Аудитор', 'ru' => 'Аудитор', 'eng' => 'Auditor'],
                'description'  => "Санҷиши ҳисоботи молиявӣ\nПроверка финансовой отчётности\nFinancial statement auditing",
            ],
            'kadrovik' => [
                'translations' => ['tj' => 'Мутахассиси кадрҳо', 'ru' => 'Специалист по кадрам', 'eng' => 'HR Specialist'],
                'description'  => "Идоракунии ҳуҷҷатҳои кадрӣ\nВедение кадрового делопроизводства\nHR records and personnel administration",
            ],

            // ── Фото и видео ─────────────────────────────────────────────
            'fotograf' => [
                'translations' => ['tj' => 'Аксбардор', 'ru' => 'Фотограф', 'eng' => 'Photographer'],
                'description'  => "Аксбардорӣ\nФотография\nPhotography",
            ],
            'videograf' => [
                'translations' => ['tj' => 'Видеограф', 'ru' => 'Видеограф', 'eng' => 'Videographer'],
                'description'  => "Видеогирӣ\nВидеосъёмка\nVideography",
            ],
            'montazher_video' => [
                'translations' => ['tj' => 'Монтажёри видео', 'ru' => 'Видеомонтажёр', 'eng' => 'Video Editor'],
                'description'  => "Монтаж ва коркарди видео\nМонтаж и обработка видеоматериала\nVideo editing and post-production",
            ],
            'retusher' => [
                'translations' => ['tj' => 'Ретушгар', 'ru' => 'Ретушёр', 'eng' => 'Photo Retoucher'],
                'description'  => "Коркарди дигиталии аксҳо\nЦифровая обработка фотографий\nDigital photo retouching",
            ],

            // ── Медицина ─────────────────────────────────────────────────
            'vrach' => [
                'translations' => ['tj' => 'Духтур', 'ru' => 'Врач', 'eng' => 'Doctor'],
                'description'  => "Хизматҳои тиббӣ\nМедицинские услуги\nMedical services",
            ],
            'medsestra' => [
                'translations' => ['tj' => 'Ҳамшираи тиббӣ', 'ru' => 'Медсестра', 'eng' => 'Nurse'],
                'description'  => "Ёрии тиббӣ\nМедицинская помощь\nNursing care",
            ],
            'stomatolog' => [
                'translations' => ['tj' => 'Дандонпизишк', 'ru' => 'Стоматолог', 'eng' => 'Dentist'],
                'description'  => "Табобат ва нигоҳубини дандон\nЛечение и уход за зубами\nDental treatment and care",
            ],
            'farmatsevt' => [
                'translations' => ['tj' => 'Дорусоз', 'ru' => 'Фармацевт', 'eng' => 'Pharmacist'],
                'description'  => "Машварат оид ба доруворӣ\nКонсультации по лекарственным препаратам\nMedication consulting",
            ],

            // ── Фитнес ───────────────────────────────────────────────────
            'personal_trainer' => [
                'translations' => ['tj' => 'Мураббии шахсӣ', 'ru' => 'Персональный тренер', 'eng' => 'Personal Trainer'],
                'description'  => "Машқҳои варзишӣ\nФитнес-тренировки\nFitness training",
            ],
            'yoga_instruktor' => [
                'translations' => ['tj' => 'Омӯзгори йога', 'ru' => 'Инструктор йоги', 'eng' => 'Yoga Instructor'],
                'description'  => "Дарсҳои йога барои ҳама сатҳҳо\nЗанятия йогой для всех уровней\nYoga classes for all levels",
            ],
            'trener_plavaniya' => [
                'translations' => ['tj' => 'Мураббии шиноварӣ', 'ru' => 'Тренер по плаванию', 'eng' => 'Swimming Coach'],
                'description'  => "Таълими шиноварӣ барои кӯдакон ва калонсолон\nОбучение плаванию для детей и взрослых\nSwimming lessons for children and adults",
            ],
            'dietolog' => [
                'translations' => ['tj' => 'Диетолог', 'ru' => 'Диетолог', 'eng' => 'Nutritionist'],
                'description'  => "Тартиб додани реҷаи ғизо\nСоставление плана питания\nMeal and nutrition planning",
            ],

            // ── Мероприятия ──────────────────────────────────────────────
            'event_manager' => [
                'translations' => ['tj' => 'Ташкилотчии чорабинӣ', 'ru' => 'Ивент-менеджер', 'eng' => 'Event Manager'],
                'description'  => "Ташкили чорабиниҳо\nОрганизация мероприятий\nEvent planning",
            ],
            'toastmaster' => [
                'translations' => ['tj' => 'Тамада', 'ru' => 'Тамада', 'eng' => 'Toastmaster'],
                'description'  => "Идораи тӯйхонаҳо\nВедение торжеств\nWedding host",
            ],
            'dj' => [
                'translations' => ['tj' => 'Диҷей', 'ru' => 'Диджей', 'eng' => 'DJ'],
                'description'  => "Пахши мусиқӣ дар чорабиниҳо\nМузыкальное сопровождение мероприятий\nMusic entertainment for events",
            ],
            'dekorator' => [
                'translations' => ['tj' => 'Ороишгари чорабинӣ', 'ru' => 'Декоратор мероприятий', 'eng' => 'Event Decorator'],
                'description'  => "Ороиши толор ва чорабинӣ\nОформление зала и мероприятий\nVenue and event decoration",
            ],
            'animator' => [
                'translations' => ['tj' => 'Аниматори кӯдакон', 'ru' => 'Детский аниматор', 'eng' => "Children's Entertainer"],
                'description'  => "Бозиву чорабинӣ барои кӯдакон\nИгровые программы для детей\nEntertainment programmes for children",
            ],

            // ── Охрана и безопасность ────────────────────────────────────
            'ohrannik' => [
                'translations' => ['tj' => 'Посбон', 'ru' => 'Охранник', 'eng' => 'Security Guard'],
                'description'  => "Хизмати посбонӣ\nОхранные услуги\nSecurity services",
            ],
            'telohranitel' => [
                'translations' => ['tj' => 'Мӯҳофиз', 'ru' => 'Телохранитель', 'eng' => 'Bodyguard'],
                'description'  => "Ҳимояи шахсии мизоҷ\nЛичная охрана клиента\nPersonal client protection",
            ],
            'montazhnik_signalizacii' => [
                'translations' => ['tj' => 'Насбкунандаи сигнализатсия', 'ru' => 'Монтажник сигнализации', 'eng' => 'Alarm and CCTV Installer'],
                'description'  => "Насби сигнализатсия ва камераҳои назорат\nУстановка сигнализации и камер видеонаблюдения\nAlarm and CCTV camera installation",
            ],
            'master_zamkov' => [
                'translations' => ['tj' => 'Қулфсоз', 'ru' => 'Мастер по замкам', 'eng' => 'Locksmith'],
                'description'  => "Кушодан ва иваз кардани қулфҳо\nВскрытие и замена замков\nLock opening and replacement",
            ],

            // ── Уход за животными ────────────────────────────────────────
            'veterinar' => [
                'translations' => ['tj' => 'Ветеринар', 'ru' => 'Ветеринар', 'eng' => 'Veterinarian'],
                'description'  => "Табобати ҳайвонот\nВетеринарная помощь\nVeterinary care",
            ],
            'groomer' => [
                'translations' => ['tj' => 'Грумер', 'ru' => 'Грумер', 'eng' => 'Groomer'],
                'description'  => "Нигоҳубини ҳайвонот\nУход за животными\nPet grooming",
            ],
            'dog_trainer' => [
                'translations' => ['tj' => 'Мураббии саг', 'ru' => 'Кинолог', 'eng' => 'Dog Trainer'],
                'description'  => "Тарбия ва омӯзиши сагҳо\nДрессировка и воспитание собак\nDog training and behaviour correction",
            ],
            'petsitter' => [
                'translations' => ['tj' => 'Нигоҳубини ҳайвонот', 'ru' => 'Петситтер', 'eng' => 'Pet Sitter'],
                'description'  => "Нигоҳубин ва гардиши ҳайвонот дар вақти набудани соҳиб\nПрисмотр и выгул животных в отсутствие хозяина\nPet sitting and walking while owner is away",
            ],

            // ── Ремонт бытовой техники (новая категория) ──────────────────
            'master_holodilnikov' => [
                'translations' => ['tj' => 'Устои яхдон', 'ru' => 'Мастер по ремонту холодильников', 'eng' => 'Refrigerator Repair Technician'],
                'description'  => "Таъмири яхдон ва фризер\nРемонт холодильников и морозильных камер\nRefrigerator and freezer repair",
            ],
            'master_stiralnyh_mashin' => [
                'translations' => ['tj' => 'Устои мошини либосшӯӣ', 'ru' => 'Мастер по ремонту стиральных машин', 'eng' => 'Washing Machine Repair Technician'],
                'description'  => "Таъмири мошини либосшӯӣ\nРемонт стиральных машин\nWashing machine repair",
            ],
            'master_konditsionerov' => [
                'translations' => ['tj' => 'Устои кондитсионер', 'ru' => 'Мастер по ремонту кондиционеров', 'eng' => 'Air Conditioner Repair Technician'],
                'description'  => "Насб, тозакунӣ ва таъмири кондитсионер\nУстановка, чистка и ремонт кондиционеров\nAC installation, cleaning and repair",
            ],
            'master_televizorov' => [
                'translations' => ['tj' => 'Устои телевизор', 'ru' => 'Мастер по ремонту телевизоров', 'eng' => 'TV Repair Technician'],
                'description'  => "Таъмири телевизор ва техникаи рӯзгор\nРемонт телевизоров и бытовой техники\nTV and home appliance repair",
            ],

            // ── Мебель (новая категория) ───────────────────────────────────
            'sborshik_mebeli' => [
                'translations' => ['tj' => 'Ҷамъкунандаи мебел', 'ru' => 'Сборщик мебели', 'eng' => 'Furniture Assembler'],
                'description'  => "Ҷамъоварии мебели нав\nСборка новой мебели\nNew furniture assembly",
            ],
            'obivshik_mebeli' => [
                'translations' => ['tj' => 'Рӯйпӯшкунандаи мебел', 'ru' => 'Обивщик мебели', 'eng' => 'Furniture Upholsterer'],
                'description'  => "Иваз кардани матои курсиву диван\nПеретяжка мягкой мебели\nFurniture reupholstering",
            ],
            'stolyar' => [
                'translations' => ['tj' => 'Дуредгар', 'ru' => 'Столяр', 'eng' => 'Carpenter'],
                'description'  => "Кор бо чӯб ва сохтани мебел\nРаботы по дереву и изготовление мебели\nWoodwork and furniture making",
            ],
            'mebelshik_na_zakaz' => [
                'translations' => ['tj' => 'Мебелсози фармоишӣ', 'ru' => 'Мебельщик на заказ', 'eng' => 'Custom Furniture Maker'],
                'description'  => "Сохтани мебели фармоишӣ мувофиқи андоза\nИзготовление мебели на заказ по размерам\nCustom-sized furniture making",
            ],

            // ── Няни и уход за детьми (новая категория) ────────────────────
            'nyanya' => [
                'translations' => ['tj' => 'Дояи бача', 'ru' => 'Няня', 'eng' => 'Nanny'],
                'description'  => "Нигоҳубини кӯдакон дар хона\nПрисмотр за детьми на дому\nIn-home childcare",
            ],
            'guvernantka' => [
                'translations' => ['tj' => 'Гувернантка', 'ru' => 'Гувернантка', 'eng' => 'Governess'],
                'description'  => "Тарбия ва таълими хонагии кӯдак\nДомашнее воспитание и обучение ребёнка\nHome education and upbringing of a child",
            ],
            'detsky_animator' => [
                'translations' => ['tj' => 'Аниматори бачагона', 'ru' => 'Детский аниматор на праздник', 'eng' => "Children's Party Entertainer"],
                'description'  => "Барномаи бозигарӣ барои зодрӯзи кӯдакон\nИгровая программа для детских праздников\nEntertainment programme for kids' parties",
            ],

            // ── Швейные услуги (новая категория) ───────────────────────────
            'shvea' => [
                'translations' => ['tj' => 'Дӯзанда', 'ru' => 'Швея', 'eng' => 'Seamstress'],
                'description'  => "Дӯхтан ва ислоҳи либос\nПошив и ремонт одежды\nClothing sewing and alteration",
            ],
            'zakroyshik' => [
                'translations' => ['tj' => 'Буришгари либос', 'ru' => 'Закройщик', 'eng' => 'Pattern Cutter'],
                'description'  => "Буриши матоъ мувофиқи андоза\nРаскрой ткани по индивидуальным меркам\nFabric cutting to custom measurements",
            ],
            'vyshivalshitsa' => [
                'translations' => ['tj' => 'Гулдӯз', 'ru' => 'Вышивальщица', 'eng' => 'Embroiderer'],
                'description'  => "Гулдӯзии дастӣ ва мошинӣ\nРучная и машинная вышивка\nHand and machine embroidery",
            ],

            // ── Переводческие услуги (новая категория) ─────────────────────
            'perevodchik_ustny' => [
                'translations' => ['tj' => 'Тарҷумони шифоҳӣ', 'ru' => 'Устный переводчик', 'eng' => 'Interpreter'],
                'description'  => "Тарҷумаи шифоҳӣ дар мулоқот ва чорабинӣ\nУстный перевод на встречах и мероприятиях\nInterpreting at meetings and events",
            ],
            'perevodchik_pismenny' => [
                'translations' => ['tj' => 'Тарҷумони хаттӣ', 'ru' => 'Письменный переводчик', 'eng' => 'Translator'],
                'description'  => "Тарҷумаи ҳуҷҷат ва матн\nПеревод документов и текстов\nDocument and text translation",
            ],
            'gid_perevodchik' => [
                'translations' => ['tj' => 'Роҳнамо-тарҷумон', 'ru' => 'Гид-переводчик', 'eng' => 'Tour Guide-Interpreter'],
                'description'  => "Роҳнамоӣ бо тарҷума барои сайёҳон\nСопровождение туристов с переводом\nTranslated tour guiding for visitors",
            ],

            // ── Кулинария и кейтеринг (новая категория) ────────────────────
            'povar' => [
                'translations' => ['tj' => 'Ошпаз', 'ru' => 'Повар', 'eng' => 'Cook'],
                'description'  => "Пухтупази хонагӣ ва фармоишӣ\nДомашняя и заказная готовка\nHome and order-based cooking",
            ],
            'konditer' => [
                'translations' => ['tj' => 'Қаннодгар', 'ru' => 'Кондитер', 'eng' => 'Confectioner'],
                'description'  => "Пухтани торт ва ширинӣ\nВыпечка тортов и десертов\nCake and dessert baking",
            ],
            'keytering_specialist' => [
                'translations' => ['tj' => 'Мутахассиси кейтеринг', 'ru' => 'Специалист кейтеринга', 'eng' => 'Catering Specialist'],
                'description'  => "Ташкили дастархон барои чорабиниҳо\nОрганизация стола для мероприятий\nEvent catering organisation",
            ],
            'barista' => [
                'translations' => ['tj' => 'Бариста', 'ru' => 'Бариста', 'eng' => 'Barista'],
                'description'  => "Тайёр кардани қаҳва барои чорабиниҳо\nПриготовление кофе для мероприятий\nCoffee service for events",
            ],

            // ── Недвижимость (новая категория) ─────────────────────────────
            'rieltor' => [
                'translations' => ['tj' => 'Риэлтор', 'ru' => 'Риэлтор', 'eng' => 'Real Estate Agent'],
                'description'  => "Хариду фурӯш ва иҷораи манзил\nПокупка, продажа и аренда недвижимости\nProperty sale, purchase and rental",
            ],
            'otsenshik_nedvizhimosti' => [
                'translations' => ['tj' => 'Баҳодиҳандаи амволи ғайриманқул', 'ru' => 'Оценщик недвижимости', 'eng' => 'Property Appraiser'],
                'description'  => "Баҳодиҳии арзиши манзил\nОценка стоимости недвижимости\nProperty value assessment",
            ],
            'upravlyayuschy_nedvizhimostyu' => [
                'translations' => ['tj' => 'Мудири амволи ғайриманқул', 'ru' => 'Управляющий недвижимостью', 'eng' => 'Property Manager'],
                'description'  => "Идораи иҷора ва хизматрасонии манзил\nУправление арендой и обслуживанием недвижимости\nRental and property management",
            ],

            // ── Ландшафт и сад (новая категория) ────────────────────────────
            'sadovnik' => [
                'translations' => ['tj' => 'Боғбон', 'ru' => 'Садовник', 'eng' => 'Gardener'],
                'description'  => "Нигоҳубини боғ ва растаниҳо\nУход за садом и растениями\nGarden and plant care",
            ],
            'landshaftny_dizayner' => [
                'translations' => ['tj' => 'Дизайнери ландшафт', 'ru' => 'Ландшафтный дизайнер', 'eng' => 'Landscape Designer'],
                'description'  => "Лоиҳакашии ҳудуди берунӣ\nПроектирование благоустройства территории\nOutdoor landscape planning",
            ],
            'agronom' => [
                'translations' => ['tj' => 'Агроном', 'ru' => 'Агроном', 'eng' => 'Agronomist'],
                'description'  => "Машварат оид ба парвариши растанӣ\nКонсультации по выращиванию растений\nPlant cultivation consulting",
            ],

            // ── Ремонт компьютеров и телефонов (новая категория) ──────────
            'master_computerov' => [
                'translations' => ['tj' => 'Устои компютер', 'ru' => 'Мастер по ремонту компьютеров', 'eng' => 'Computer Repair Technician'],
                'description'  => "Таъмир ва тозакунии компютер\nРемонт и чистка компьютеров и ноутбуков\nComputer and laptop repair and cleaning",
            ],
            'master_telefonov' => [
                'translations' => ['tj' => 'Устои телефон', 'ru' => 'Мастер по ремонту телефонов', 'eng' => 'Phone Repair Technician'],
                'description'  => "Таъмири экран, батарея ва дигар қисмҳои телефон\nРемонт экрана, батареи и других деталей телефона\nScreen, battery and component phone repair",
            ],
            'master_planshetov' => [
                'translations' => ['tj' => 'Устои планшет', 'ru' => 'Мастер по ремонту планшетов', 'eng' => 'Tablet Repair Technician'],
                'description'  => "Таъмири планшет ва дастгоҳҳои дигар\nРемонт планшетов и других устройств\nTablet and device repair",
            ],
            'master_printerov' => [
                'translations' => ['tj' => 'Устои принтер', 'ru' => 'Мастер по ремонту принтеров', 'eng' => 'Printer Repair Technician'],
                'description'  => "Таъмир ва пуркунии картриҷ\nРемонт принтеров и заправка картриджей\nPrinter repair and cartridge refilling",
            ],

            // ── Разнорабочие услуги (новая категория) ──────────────────────
            'raznorabochiy' => [
                'translations' => ['tj' => 'Коргари ёрирасон', 'ru' => 'Разнорабочий', 'eng' => 'General Labourer'],
                'description'  => "Кӯмаки жисмонӣ дар корҳои гуногун\nФизическая помощь в разных видах работ\nPhysical help with various odd jobs",
            ],
            'podsobnik' => [
                'translations' => ['tj' => 'Ёрдамчии сохтмон', 'ru' => 'Подсобный рабочий', 'eng' => "Construction Helper"],
                'description'  => "Кӯмак дар сохтмон ва боркашонӣ\nПомощь на стройке и при переносе грузов\nConstruction site and moving help",
            ],
            'master_na_chas' => [
                'translations' => ['tj' => 'Уста барои корҳои хурд', 'ru' => 'Мастер на час', 'eng' => 'Handyman'],
                'description'  => "Ислоҳи хурди хона: овезон кардани рафҳо, мебел ва ғайра\nМелкий бытовой ремонт: полки, мебель, фурнитура\nSmall household fixes: shelves, furniture, fittings",
            ],

            // ── Ювелирные и часовые услуги (новая категория) ────────────────
            'yuvelir' => [
                'translations' => ['tj' => 'Заргар', 'ru' => 'Ювелир', 'eng' => 'Jeweller'],
                'description'  => "Таъмир ва сохтани заргарӣ\nРемонт и изготовление ювелирных изделий\nJewellery repair and crafting",
            ],
            'chasovshik' => [
                'translations' => ['tj' => 'Соатсоз', 'ru' => 'Часовщик', 'eng' => 'Watchmaker'],
                'description'  => "Таъмир ва танзими соат\nРемонт и настройка часов\nWatch repair and adjustment",
            ],
            'graver' => [
                'translations' => ['tj' => 'Кандакор', 'ru' => 'Гравёр', 'eng' => 'Engraver'],
                'description'  => "Кандакорӣ рӯи металл ва сангҳои қиматбаҳо\nГравировка на металле и драгоценных камнях\nEngraving on metal and gemstones",
            ],

            // ── Психология и консультации (новая категория) ─────────────────
            'psycholog' => [
                'translations' => ['tj' => 'Равоншинос', 'ru' => 'Психолог', 'eng' => 'Psychologist'],
                'description'  => "Машваратҳои равоншиносӣ\nПсихологические консультации\nPsychological counselling",
            ],
            'semeynyy_konsultant' => [
                'translations' => ['tj' => 'Мушовири оилавӣ', 'ru' => 'Семейный консультант', 'eng' => 'Family Counsellor'],
                'description'  => "Машварат оид ба муносибатҳои оилавӣ\nКонсультации по семейным отношениям\nFamily relationship counselling",
            ],
            'coach' => [
                'translations' => ['tj' => 'Коуч', 'ru' => 'Коуч личностного роста', 'eng' => 'Personal Development Coach'],
                'description'  => "Кӯмак дар рушди шахсӣ ва касбӣ\nПомощь в личностном и карьерном росте\nPersonal and career growth coaching",
            ],

            // ── Печать и полиграфия (новая категория) ───────────────────────
            'tipograf' => [
                'translations' => ['tj' => 'Чопгар', 'ru' => 'Печатник', 'eng' => 'Printer Operator'],
                'description'  => "Чопи маводи рекламавӣ ва ҳуҷҷатҳо\nПечать рекламной продукции и документов\nPrinting of promo materials and documents",
            ],
            'dizayner_pechati' => [
                'translations' => ['tj' => 'Дизайнери маводи чопӣ', 'ru' => 'Дизайнер печатной продукции', 'eng' => 'Print Designer'],
                'description'  => "Тарҳрезии баннер, буклет ва варақа\nВёрстка баннеров, буклетов и листовок\nBanner, booklet and flyer layout design",
            ],

            // ── Обувные и кожаные изделия (новая категория) ─────────────────
            'sapozhnik' => [
                'translations' => ['tj' => 'Мӯзадӯз', 'ru' => 'Сапожник', 'eng' => 'Shoemaker'],
                'description'  => "Таъмир ва тозакунии пойафзол\nРемонт и чистка обуви\nShoe repair and cleaning",
            ],
            'kozhevnik' => [
                'translations' => ['tj' => 'Устои чарм', 'ru' => 'Мастер по коже', 'eng' => 'Leather Craftsman'],
                'description'  => "Таъмири сумка, камарбанд ва дигар маснуоти чармӣ\nРемонт сумок, ремней и других изделий из кожи\nRepair of bags, belts and other leather goods",
            ],
        ];

        foreach ($occupationsData as $key => $data) {
            $occupation = new Occupation();

            $reflection = new ReflectionClass($occupation);
            /** @noinspection PhpStatementHasEmptyBodyInspection */
            while (!$reflection->hasProperty('translations') && $reflection = $reflection->getParentClass());
            $property = $reflection->getProperty('translations');
            $property->setValue($occupation, new ArrayCollection());

            foreach ($data['translations'] as $locale => $title) {
                $translation = (new Translation())
                    ->setTitle($title)
                    ->setLocale($locale)
                    ->setOccupation($occupation->setDescription($data['description']));

                $occupation->addTranslation($translation);
            }

            $manager->persist($occupation);
            $this->addReference($key, $occupation);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [];
    }
}
