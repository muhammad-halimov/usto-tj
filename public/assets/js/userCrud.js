// Загрузка страницы
document.addEventListener('DOMContentLoaded', async () => {
    await loadAndChangeChoices();
});

async function loadAndChangeChoices() {
    // Чекбоксы ролей ищем по value, а не по индексу User_roles_N — индекс
    // зависит от порядка объявления User::ROLES в сущности и слетает при
    // любой перестановке/добавлении роли (как только что случилось с
    // добавлением ROLE_SUPER_ADMIN первым элементом массива).
    const roleInput = (role) => document.querySelector(`input[name="User[roles][]"][value="${role}"]`);

    const superAdminOption = roleInput('ROLE_SUPER_ADMIN');
    const adminOption      = roleInput('ROLE_ADMIN');
    const masterOption     = roleInput('ROLE_MASTER');
    const clientOption     = roleInput('ROLE_CLIENT');

    const remotelyOption     = document.getElementById('User_remotely');
    const occupationDropDown = document.querySelector('.occupation-field');

    if (!superAdminOption || !adminOption || !masterOption || !clientOption) return;

    // <label for="User_roles_0">...</label> — отдельный от инпута элемент.
    // Клик по лейблу браузер делегирует в input.click() по спеке — это
    // работает, даже если у инпута выставлен только style.pointerEvents:none
    // (он блокирует хиты по самому инпуту, но не активацию через label).
    // Единственное, что реально останавливает делегирование от label —
    // настоящий HTML-атрибут disabled на самом инпуте.
    const relatedLabel = (el) => el?.id ? document.querySelector(`label[for="${el.id}"]`) : null;

    const disableDropDown = (dropDown) => {
        if (!dropDown) return;
        dropDown.style.pointerEvents = 'none';
        dropDown.style.opacity = '0.4';
        dropDown.disabled = true;
        dropDown.querySelectorAll('select, input, textarea').forEach(el => el.disabled = true);

        const label = relatedLabel(dropDown);
        if (label) {
            label.style.pointerEvents = 'none';
            label.style.opacity = '0.4';
        }
    };

    const enableDropDown = (dropDown) => {
        if (!dropDown) return;
        dropDown.style.pointerEvents = 'auto';
        dropDown.style.opacity = '1';
        dropDown.disabled = false;
        dropDown.querySelectorAll('select, input, textarea').forEach(el => el.disabled = false);

        const label = relatedLabel(dropDown);
        if (label) {
            label.style.pointerEvents = 'auto';
            label.style.opacity = '1';
        }
    };

    // Две непересекающиеся группы ролей:
    //   personal — CLIENT/MASTER: строго одна роль, ни с чем не сочетается
    //              (в том числе друг с другом);
    //   admin    — ADMIN/SUPER_ADMIN: сочетаются друг с другом, но не с personal.
    //
    // Разрешено: [CLIENT]  [MASTER]  [ADMIN]  [SUPER_ADMIN]  [ADMIN, SUPER_ADMIN]
    // Запрещено: [ADMIN, CLIENT]  [ADMIN, MASTER]  [MASTER, CLIENT]
    //            (и любая другая смесь personal- и admin-ролей)
    const personalRoles = [masterOption, clientOption];
    const adminRoles    = [adminOption, superAdminOption];
    const allRoleInputs = [...personalRoles, ...adminRoles];

    const updateDropDowns = () => {
        // Сначала сбрасываем всё в исходное состояние — иначе переключение
        // между ролями с разными побочными эффектами (occupation/remotely
        // отключаются только для client) могло оставлять их "залипшими"
        // отключёнными после смены роли.
        allRoleInputs.forEach(enableDropDown);
        enableDropDown(occupationDropDown);
        enableDropDown(remotelyOption);

        const personalChecked = personalRoles.find(el => el.checked);
        const adminChecked    = adminRoles.some(el => el.checked);

        if (personalChecked) {
            // CLIENT или MASTER выбраны — блокируем все остальные роли,
            // включая вторую personal-роль (CLIENT+MASTER тоже запрещено).
            allRoleInputs.filter(el => el !== personalChecked).forEach(disableDropDown);

            if (personalChecked === clientOption) {
                disableDropDown(occupationDropDown);
                disableDropDown(remotelyOption);
            }
        } else if (adminChecked) {
            // ADMIN и/или SUPER_ADMIN выбраны — блокируем только personal-роли,
            // друг с другом ADMIN/SUPER_ADMIN сочетаться могут.
            personalRoles.forEach(disableDropDown);
        }
    };

    allRoleInputs.forEach(el => el.addEventListener('change', updateDropDowns));

    updateDropDowns();
}
