/**
 * Funções globais do portal
 */

// Exibe notificações toast com SweetAlert2 (já incluído no footer)
function showToast(message, icon = 'success') {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: icon,
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}

// Formata número com duas casas decimais
function formatNumber(num) {
    return parseFloat(num || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}