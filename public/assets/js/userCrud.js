// Загрузка страницы
document.addEventListener('DOMContentLoaded', async () => {
    await loadAndChangeChoices();
});

async function loadAndChangeChoices() {
    const adminOption = document.getElementById('User_roles_0');
    const masterOption = document.getElementById('User_roles_1');
    const clientOption = document.getElementById('User_roles_2');

    const disableDropDown = (dropDown) => {
        dropDown.style.pointerEvents = 'none';
        dropDown.style.opacity = '0.4';
        dropDown.querySelectorAll('select, input, textarea').forEach(el => el.disabled = true);
    };

    const enableDropDown = (dropDown) => {
        dropDown.style.pointerEvents = 'auto';
        dropDown.style.opacity = '1';
        dropDown.querySelectorAll('select, input, textarea').forEach(el => el.disabled = false);
    };

    const updateDropDowns = () => {
        if (adminOption.checked) {
            disableDropDown(masterOption);
            disableDropDown(clientOption);
        } else if(masterOption.checked) {
            disableDropDown(adminOption);
            disableDropDown(clientOption);
        } else if(clientOption.checked) {
            disableDropDown(masterOption);
            disableDropDown(adminOption);
        } else {
            // Если ничего не выбрано — отключаем
            enableDropDown(adminOption);
            enableDropDown(clientOption);
            enableDropDown(masterOption);
        }
    };

    // Обработчики переключения
    adminOption.addEventListener('change', updateDropDowns);
    masterOption.addEventListener('change', updateDropDowns);
    clientOption.addEventListener('change', updateDropDowns);

    // Автоматически выбираем клиентскую опцию при загрузке
    clientOption.checked = true;
    updateDropDowns();
}
