// Auto-fetch if order_id parameter exists in URL
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const orderId = urlParams.get('order_id');
    if (orderId) {
        document.getElementById('orderInput').value = orderId;
        fetchStatus();
    }
});

async function fetchStatus() {
    const orderId = document.getElementById('orderInput').value.trim();
    const resultDiv = document.getElementById('statusResult');
    const errDiv = document.getElementById('errorMessage');
    
    if (!orderId) return;

    errDiv.classList.add('hidden');
    resultDiv.classList.add('hidden');

    try {
        const res = await fetch(`../api/track_order.php?order_id=${encodeURIComponent(orderId)}`);
        const data = await res.json();

        if (!data.success) {
            errDiv.innerText = data.message;
            errDiv.classList.remove('hidden');
            return;
        }

        document.getElementById('resOrderId').innerText = data.order_id;
        document.getElementById('resName').innerText = data.customer_name;
        document.getElementById('percentText').innerText = `${data.progress_percent}%`;
        document.getElementById('progressBar').style.width = `${data.progress_percent}%`;

        const stepsList = document.getElementById('stepsList');
        stepsList.innerHTML = '';

        let passedCurrent = false;
        Object.keys(data.steps).forEach((key) => {
            const step = data.steps[key];
            const isCurrent = (key === data.current_status);
            
            let cardState = 'pending';
            let statusIcon = '⚪';

            if (isCurrent) {
                cardState = 'active';
                statusIcon = '🔄';
            } else if (!passedCurrent) {
                cardState = 'completed';
                statusIcon = '✅';
            }

            stepsList.innerHTML += `
                <div class="step-card ${cardState}">
                    <span>${statusIcon}</span>
                    <div>
                        <div class="step-title">${step.title}</div>
                        <div class="step-desc">${step.desc}</div>
                    </div>
                </div>
            `;

            if (isCurrent) passedCurrent = true;
        });

        resultDiv.classList.remove('hidden');

    } catch (e) {
        errDiv.innerText = 'Gagal memuatkan status. Sila semak sambungan internet anda.';
        errDiv.classList.remove('hidden');
    }
}