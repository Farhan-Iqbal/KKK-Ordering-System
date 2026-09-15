document.addEventListener('DOMContentLoaded', function () {
    let currentSelectedCard = 1;

    // -----------------------------------------------------------------
    // 1. INITIALIZATION & SETUP
    // -----------------------------------------------------------------
    initEventListeners();
    handlePackageChange();
    toggleShippingSection();
    updateLivePreview();

    // Bootstrap Form Validation Enforcer
    const form = document.getElementById('orderForm');
    if (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    }

    // -----------------------------------------------------------------
    // 2. EVENT LISTENERS
    // -----------------------------------------------------------------
    function initEventListeners() {
        // Toggle Buttons (Front / Back Card View)
        const btnFront = document.getElementById('btnFront');
        const btnBack = document.getElementById('btnBack');

        if (btnFront) btnFront.addEventListener('click', () => toggleCardView('front'));
        if (btnBack) btnBack.addEventListener('click', () => toggleCardView('back'));

        // Toggle Buttons (Card 1 vs Card 2 for Dual Package)
        const btnCard1 = document.getElementById('btnViewCard1');
        const btnCard2 = document.getElementById('btnViewCard2');

        if (btnCard1) btnCard1.addEventListener('click', () => switchActiveCard(1));
        if (btnCard2) btnCard2.addEventListener('click', () => switchActiveCard(2));

        // Pakej & Side Dropdown listeners
        const pakejChoice = document.getElementById('pakejChoice');
        if (pakejChoice) pakejChoice.addEventListener('change', handlePackageChange);

        const majlis1Side = document.getElementById('majlis1Side');
        if (majlis1Side) majlis1Side.addEventListener('change', handleSideSwap);

        // Radio delivery listener
        const deliveryRadios = document.getElementsByName('delivery_method');
        deliveryRadios.forEach(radio => radio.addEventListener('change', toggleShippingSection));

        // Comprehensive array of field inputs for live rendering
        const inputIds = [
            'tajukMajlis', 'namaLelaki', 'singkatanLelaki', 'namaPerempuan', 'singkatanPerempuan',
            'namaBapa', 'namaIbu', 'hariMajlis', 'tarikhMajlis', 'tarikhHijrah', 'masaJamuan', 'masaBersanding', 'alamatMajlis',
            'namaHubungi1', 'noHubungi1', 'namaHubungi2', 'noHubungi2', 'namaHubungi3', 'noHubungi3',
            'namaBapa1', 'namaIbu1', 'hariMajlis1', 'tarikhMajlis1', 'tarikhHijrah1', 'masaJamuan1', 'masaBersanding1', 'alamatMajlis1',
            'namaHubungi1_m1', 'noHubungi1_m1', 'namaHubungi2_m1', 'noHubungi2_m1', 'namaHubungi3_m1', 'noHubungi3_m1',
            'namaBapa2', 'namaIbu2', 'hariMajlis2', 'tarikhMajlis2', 'tarikhHijrah2', 'masaJamuan2', 'masaBersanding2', 'alamatMajlis2',
            'namaHubungi1_m2', 'noHubungi1_m2', 'namaHubungi2_m2', 'noHubungi2_m2', 'namaHubungi3_m2', 'noHubungi3_m2'
        ];

        inputIds.forEach(id => {
            const elem = document.getElementById(id);
            if (elem) {
                elem.addEventListener('input', updateLivePreview);
                elem.addEventListener('change', updateLivePreview);
                elem.addEventListener('blur', updateLivePreview);
            }
        });
    }

    // -----------------------------------------------------------------
    // 3. PACKAGE & CONTAINER SWITCHING
    // -----------------------------------------------------------------
    function handlePackageChange() {
        const pakejChoice = getInputValue('pakejChoice', '');
        const singleContainer = document.getElementById('singlePartyContainer');
        const dualContainer = document.getElementById('dualPartyContainer');
        const cardSelectorWrapper = document.getElementById('cardSelectorWrapper');

        const isDual = pakejChoice.includes('Kedua-dua Pihak');

        if (isDual) {
            singleContainer?.classList.add('d-none');
            dualContainer?.classList.remove('d-none');
            cardSelectorWrapper?.classList.remove('d-none');
            setRequiredFields('.dual-req', true);
            setRequiredFields('.single-req', false);
        } else {
            singleContainer?.classList.remove('d-none');
            dualContainer?.classList.add('d-none');
            cardSelectorWrapper?.classList.add('d-none');
            currentSelectedCard = 1;
            setRequiredFields('.single-req', true);
            setRequiredFields('.dual-req', false);
        }

        updateLivePreview();
    }

    function handleSideSwap() {
        const majlis1Val = getInputValue('majlis1Side', 'Pihak Lelaki');
        const majlis2Label = document.getElementById('majlis2SideLabel');

        if (majlis1Val === 'Pihak Lelaki') {
            if (majlis2Label) majlis2Label.innerText = "Pihak Bagi Majlis Kedua: Pihak Perempuan";
        } else {
            if (majlis2Label) majlis2Label.innerText = "Pihak Bagi Majlis Kedua: Pihak Lelaki";
        }

        updateLivePreview();
    }

    function toggleShippingSection() {
        const deliveryPickup = document.getElementById('deliveryPickup');
        const shippingSection = document.getElementById('shippingAddressSection');
        const shippingInputs = shippingSection?.querySelectorAll('input, textarea, select');

        if (deliveryPickup && deliveryPickup.checked) {
            shippingSection?.classList.add('d-none');
            shippingInputs?.forEach(input => input.removeAttribute('required'));
        } else {
            shippingSection?.classList.remove('d-none');
            shippingInputs?.forEach(input => input.setAttribute('required', 'required'));
        }
    }

    function setRequiredFields(selector, isRequired) {
        document.querySelectorAll(selector).forEach(elem => {
            if (isRequired) elem.setAttribute('required', 'required');
            else elem.removeAttribute('required');
        });
    }

    // -----------------------------------------------------------------
    // 4. CARD PREVIEW TOGGLES
    // -----------------------------------------------------------------
    function toggleCardView(view) {
        const cardFront = document.getElementById('cardFrontView');
        const cardBack = document.getElementById('cardBackView');
        const btnFront = document.getElementById('btnFront');
        const btnBack = document.getElementById('btnBack');

        if (view === 'front') {
            cardFront?.classList.remove('d-none');
            cardBack?.classList.add('d-none');
            btnFront?.classList.add('active', 'btn-primary');
            btnFront?.classList.remove('btn-outline-primary');
            btnBack?.classList.remove('active', 'btn-primary');
            btnBack?.classList.add('btn-outline-primary');
        } else {
            cardFront?.classList.add('d-none');
            cardBack?.classList.remove('d-none');
            btnBack?.classList.add('active', 'btn-primary');
            btnBack?.classList.remove('btn-outline-primary');
            btnFront?.classList.remove('active', 'btn-primary');
            btnFront?.classList.add('btn-outline-primary');
        }
    }

    function switchActiveCard(cardNumber) {
        currentSelectedCard = cardNumber;
        const btnCard1 = document.getElementById('btnViewCard1');
        const btnCard2 = document.getElementById('btnViewCard2');

        if (cardNumber === 1) {
            btnCard1?.classList.add('active', 'btn-dark');
            btnCard1?.classList.remove('btn-outline-dark');
            btnCard2?.classList.remove('active', 'btn-dark');
            btnCard2?.classList.add('btn-outline-dark');
        } else {
            btnCard2?.classList.add('active', 'btn-dark');
            btnCard2?.classList.remove('btn-outline-dark');
            btnCard1?.classList.remove('active', 'btn-dark');
            btnCard1?.classList.add('btn-outline-dark');
        }

        updateLivePreview();
    }

    // -----------------------------------------------------------------
    // 5. CORE LIVE PREVIEW UPDATE LOGIC
    // -----------------------------------------------------------------
    function updateLivePreview() {
        const pakejChoice = getInputValue('pakejChoice', '');
        const isDual = pakejChoice.includes('Kedua-dua Pihak');

        const title = getInputValue('tajukMajlis', 'Walimatul Urus');
        const g1Full = getInputValue('namaLelaki', 'NAMA PENGANTIN LELAKI');
        const g1Short = getInputValue('singkatanLelaki', 'Syafiq');
        const g2Full = getInputValue('namaPerempuan', 'NAMA PENGANTIN PEREMPUAN');
        const g2Short = getInputValue('singkatanPerempuan', 'Awanis');

        let bapa = 'NAMA BAPA';
        let ibu = '';
        let dayName = 'SABTU';
        let dateVal = '';
        let hijriStr = '4 ZULHIJAH 1447H';
        let timeJamuan = '11:30 AM - 3:30 PM';
        let timeSanding = '12:30 PM';
        let address = 'NO. 87, LALUAN TAMAN MERU 8, TAMAN MERU 2B, 30020 IPOH PERAK';
        let contacts = [];

        let topShort = g1Short;
        let bottomShort = g2Short;
        let topFull = g1Full;
        let bottomFull = g2Full;

        if (!isDual) {
            bapa = getInputValue('namaBapa', 'NAMA BAPA');
            ibu = getInputValue('namaIbu', '');
            dayName = getInputValue('hariMajlis', 'SABTU');
            dateVal = getInputValue('tarikhMajlis', '');
            hijriStr = getInputValue('tarikhHijrah', '4 ZULHIJAH 1447H');
            timeJamuan = getInputValue('masaJamuan', '11:30 AM - 3:30 PM');
            timeSanding = getInputValue('masaBersanding', '12:30 PM');
            address = getInputValue('alamatMajlis', 'NO. 87, LALUAN TAMAN MERU 8, TAMAN MERU 2B, 30020 IPOH PERAK');

            if (pakejChoice.includes('Perempuan')) {
                topShort = g2Short;
                bottomShort = g1Short;
                topFull = g2Full;
                bottomFull = g1Full;
            }

            contacts = [
                { name: getInputValue('namaHubungi1', 'AHLI 1'), phone: getInputValue('noHubungi1', '017-2345678') },
                { name: getInputValue('namaHubungi2', 'AHLI 2'), phone: getInputValue('noHubungi2', '017-2345678') },
                { name: getInputValue('namaHubungi3', ''), phone: getInputValue('noHubungi3', '') }
            ].filter(c => c.name || c.phone);

        } else {
            const m1Side = getInputValue('majlis1Side', 'Pihak Lelaki');
            const isMajlis1Lelaki = (m1Side === 'Pihak Lelaki');
            const targetMajlis = currentSelectedCard;

            if (targetMajlis === 1) {
                bapa = getInputValue('namaBapa1', 'NAMA BAPA MAJLIS 1');
                ibu = getInputValue('namaIbu1', '');
                dayName = getInputValue('hariMajlis1', 'SABTU');
                dateVal = getInputValue('tarikhMajlis1', '');
                hijriStr = getInputValue('tarikhHijrah1', '4 ZULHIJAH 1446H');
                timeJamuan = getInputValue('masaJamuan1', '11:30 AM - 3:30 PM');
                timeSanding = getInputValue('masaBersanding1', '12:30 PM');
                address = getInputValue('alamatMajlis1', 'NO. 87, LALUAN TAMAN MERU 8, TAMAN MERU 2B, 30020 IPOH PERAK');

                if (!isMajlis1Lelaki) {
                    topShort = g2Short; bottomShort = g1Short;
                    topFull = g2Full; bottomFull = g1Full;
                }

                contacts = [
                    { name: getInputValue('namaHubungi1_m1', 'AHLI 1'), phone: getInputValue('noHubungi1_m1', '017-2345678') },
                    { name: getInputValue('namaHubungi2_m1', 'AHLI 2'), phone: getInputValue('noHubungi2_m1', '017-2345678') },
                    { name: getInputValue('namaHubungi3_m1', ''), phone: getInputValue('noHubungi3_m1', '') }
                ].filter(c => c.name || c.phone);

            } else {
                bapa = getInputValue('namaBapa2', 'NAMA BAPA MAJLIS 2');
                ibu = getInputValue('namaIbu2', '');
                dayName = getInputValue('hariMajlis2', 'AHAD');
                dateVal = getInputValue('tarikhMajlis2', '');
                hijriStr = getInputValue('tarikhHijrah2', '5 ZULHIJAH 1446H');
                timeJamuan = getInputValue('masaJamuan2', '11:30 AM - 3:30 PM');
                timeSanding = getInputValue('masaBersanding2', '12:30 PM');
                address = getInputValue('alamatMajlis2', 'ALAMAT MAJLIS KEDUA');

                if (isMajlis1Lelaki) {
                    topShort = g2Short; bottomShort = g1Short;
                    topFull = g2Full; bottomFull = g1Full;
                }

                contacts = [
                    { name: getInputValue('namaHubungi1_m2', 'AHLI 1'), phone: getInputValue('noHubungi1_m2', '017-2345678') },
                    { name: getInputValue('namaHubungi2_m2', 'AHLI 2'), phone: getInputValue('noHubungi2_m2', '017-2345678') },
                    { name: getInputValue('namaHubungi3_m2', ''), phone: getInputValue('noHubungi3_m2', '') }
                ].filter(c => c.name || c.phone);
            }
        }

        // Date Format Handling - Prevents UTC shift bug
        let dayNum = "16";
        let monthYearStr = "SEPT 2026";
        let fullDateStr = "16 SEPTEMBER 2026";

        if (dateVal) {
            const dateParts = dateVal.split('-');
            if (dateParts.length === 3) {
                const year = parseInt(dateParts[0], 10);
                const monthIdx = parseInt(dateParts[1], 10) - 1;
                const day = parseInt(dateParts[2], 10);

                const monthsInMalay = ['JANUARI', 'FEBRUARI', 'MAC', 'APRIL', 'MEI', 'JUN', 'JULAI', 'OGOS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DISEMBER'];
                const shortMonthsInMalay = ['JAN', 'FEB', 'MAC', 'APR', 'MEI', 'JUN', 'JUL', 'OGOS', 'SEPT', 'OKT', 'NOV', 'DIS'];

                if (!isNaN(day) && !isNaN(monthIdx) && monthIdx >= 0 && monthIdx < 12 && !isNaN(year)) {
                    dayNum = String(day).padStart(2, '0');
                    monthYearStr = `${shortMonthsInMalay[monthIdx]} ${year}`;
                    fullDateStr = `${dayNum} ${monthsInMalay[monthIdx]} ${year}`;
                }
            }
        }

        // DOM Front Card Rendering
        setDOMText('prevFrontTitle', title);
        setDOMText('prevFrontTopName', topShort);
        setDOMText('prevFrontBottomName', bottomShort);
        setDOMText('prevFrontDay', dayName.toUpperCase());
        setDOMText('prevFrontDate', fullDateStr);

        // DOM Back Card Rendering
        const parentsElem = document.getElementById('prevBackParents');
        if (parentsElem) {
            parentsElem.innerHTML = ibu ? `${escapeHTML(bapa)}<br>&<br>${escapeHTML(ibu)}` : escapeHTML(bapa);
        }

        const coupleElem = document.getElementById('prevBackFullCouple');
        if (coupleElem) {
            coupleElem.innerHTML = `${escapeHTML(topFull)}<br><span class="back-couple-sub">dengan pasangannya</span><br>${escapeHTML(bottomFull)}`;
        }

        setDOMText('prevColDay', dayName.toUpperCase());
        setDOMText('prevColNum', dayNum);
        setDOMText('prevColMonthYear', monthYearStr);
        setDOMText('prevColHijri', hijriStr);
        setDOMText('prevColTimeJamuan', timeJamuan);
        setDOMText('prevColTimeSanding', timeSanding);
        setDOMText('prevBackLocation', address);

        renderContacts(contacts);
    }

    // -----------------------------------------------------------------
    // 6. HELPER FUNCTIONS
    // -----------------------------------------------------------------
    function getInputValue(id, fallback = '') {
        const field = document.getElementById(id);
        if (!field || !field.value.trim()) return fallback;
        return field.value.trim();
    }

    function setDOMText(id, value) {
        const elem = document.getElementById(id);
        if (elem) elem.innerText = value;
    }

    function escapeHTML(str) {
        return str.replace(/[&<>'"]/g, 
            tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
        );
    }

    function renderContacts(contactsList) {
        const contactsContainer = document.getElementById('prevColContacts');
        if (!contactsContainer) return;

        let html = '';
        contactsList.forEach((c, idx) => {
            const cName = c.name || `HUBUNGI ${idx + 1}`;
            const cPhone = c.phone || '01X-XXXXXXX';
            html += `<div class="col-contact-item"><strong>${escapeHTML(cName.toUpperCase())}</strong>${escapeHTML(cPhone)}</div>`;
        });

        contactsContainer.innerHTML = html;
    }
});