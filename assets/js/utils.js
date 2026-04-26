// assets/js/utils.js

/**
 * Display a toast notification (requires Toastify JS to be loaded, or falls back to alert)
 */
function showToast(message, type = 'info') {
    if (typeof Toastify !== 'undefined') {
        let bgColors = {
            'success': 'linear-gradient(to right, #10b981, #059669)',
            'error': 'linear-gradient(to right, #ef4444, #dc2626)',
            'info': 'linear-gradient(to right, #3b82f6, #2563eb)'
        };
        
        Toastify({
            text: message,
            duration: 3000,
            close: true,
            gravity: "top", 
            position: "right",
            style: {
                background: bgColors[type] || bgColors['info']
            }
        }).showToast();
    } else {
        alert(message);
    }
}

/**
 * Format a number as Indian Rupee (INR)
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(amount);
}

/**
 * Wrapper for fetch API to handle common JSON requests
 */
async function api(endpoint, data = null, method = 'POST') {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };

    // If there's a CSRF token variable in the global scope, add it
    if (typeof CSRF_TOKEN !== 'undefined') {
        options.headers['X-CSRF-Token'] = CSRF_TOKEN;
    }

    if (data) {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(endpoint, options);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}
