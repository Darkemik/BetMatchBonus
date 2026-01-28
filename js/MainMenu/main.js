
function updateDateTime() {
  const now = new Date();
  const formatted = now.toLocaleString('hu-HU', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
  document.getElementById('currentDateTime').innerText = formatted;
}

updateDateTime();
setInterval(updateDateTime, 1000);

