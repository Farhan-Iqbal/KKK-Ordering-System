document.addEventListener("DOMContentLoaded", function () {
    const STAFF_WHATSAPP_NUMBER = "60186684036";

    // Extract Order Parameters from URL
    const urlParams = new URLSearchParams(window.location.search);
    const orderId = urlParams.get('order_id') || "N/A";
    let customerName = urlParams.get('name');
    let coupleName = urlParams.get('couple');
    let orderSide = urlParams.get('side');

    const orderRefElem = document.getElementById('orderRefDisplay');
    if (orderRefElem) {
        orderRefElem.innerText = `Order Reference: ${orderId}`;
    }
    
    // Pass order_id dynamically to tracking page button
    const trackBtn = document.getElementById('trackBtn');
    if (trackBtn && orderId !== "N/A") {
        trackBtn.href = `track.html?order_id=${encodeURIComponent(orderId)}`;
    }

    function updateUI(name, couple, side) {
        const summaryName = document.getElementById('summaryName');
        const summaryCouple = document.getElementById('summaryCouple');
        const summarySide = document.getElementById('summarySide');
        const whatsappBtn = document.getElementById('whatsappBtn');

        if (summaryName) summaryName.innerText = name || "N/A";
        if (summaryCouple) summaryCouple.innerText = couple || "N/A";
        if (summarySide) summarySide.innerText = side || "N/A";

        // Build dynamic WhatsApp pre-filled message
        const waText = 
`Hello KingKadKahwin Team, I have submitted my wedding card order details and deposit receipt.

*ORDER DETAILS*
• *Order ID:* ${orderId}
• *Name:* ${name || 'N/A'}
• *Couple:* ${couple || 'N/A'}
• *Package / Side:* ${side || 'N/A'}

Please review my order. Thank you!`;

        const encodedMessage = encodeURIComponent(waText);
        if (whatsappBtn) {
            whatsappBtn.href = `https://wa.me/${STAFF_WHATSAPP_NUMBER}?text=${encodedMessage}`;
        }
    }

    // Fetch order details if parameters are missing from URL
    if ((!customerName || !coupleName || !orderSide) && orderId !== "N/A") {
        fetch(`../api/get_order_details.php?order_id=${encodeURIComponent(orderId)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.order) {
                    const cName = data.order.customer_name || customerName;
                    let cCouple = "N/A";

                    if (data.details) {
                        // Extract short names using groom_abbrev and bride_abbrev (with fallback to full names)
                        const groom = data.details.groom_abbrev || data.details.groom_name;
                        const bride = data.details.bride_abbrev || data.details.bride_name;

                        // Identify host side to determine name sequence
                        const hostSide = (data.details.m1_host_side || data.order.side_type || '').toLowerCase();

                        if (groom && bride) {
                            if (hostSide.includes('perempuan')) {
                                // Bride short name first if Pihak Perempuan is hosting
                                cCouple = `${bride} & ${groom}`;
                            } else {
                                // Groom short name first if Pihak Lelaki is hosting or default
                                cCouple = `${groom} & ${bride}`;
                            }
                        } else if (groom) {
                            cCouple = groom;
                        } else if (bride) {
                            cCouple = bride;
                        } else {
                            cCouple = cName || "N/A";
                        }
                    } else {
                        cCouple = cName || "N/A";
                    }

                    // Format Side / Package Option
                    const pkg = data.order.package_type || '';
                    const sideType = data.order.side_type || '';
                    const cSide = pkg ? `${pkg} (${sideType})`.trim() : (sideType || orderSide || "N/A");

                    updateUI(cName, cCouple, cSide);
                } else {
                    updateUI(customerName, coupleName, orderSide);
                }
            })
            .catch(err => {
                console.error("Failed to fetch order details:", err);
                updateUI(customerName, coupleName, orderSide);
            });
    } else {
        updateUI(customerName, coupleName, orderSide);
    }
});