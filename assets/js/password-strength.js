// Password Strength Meter
// Usage: add class "password-meter" to any password input
// It will auto-inject a strength bar below the input
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.password-meter').forEach(function(input) {
        // Create meter container
        const meter = document.createElement('div');
        meter.style.cssText = 'margin-top:6px;';
        const bar = document.createElement('div');
        bar.style.cssText = 'height:4px;border-radius:99px;background:#e2e8f0;overflow:hidden;';
        const fill = document.createElement('div');
        fill.style.cssText = 'height:100%;width:0%;border-radius:99px;transition:all .3s ease;';
        bar.appendChild(fill);
        const label = document.createElement('div');
        label.style.cssText = 'font-size:.66rem;font-weight:700;margin-top:3px;transition:color .3s;color:#94a3b8;';
        label.textContent = '';
        meter.appendChild(bar);
        meter.appendChild(label);
        input.parentNode.insertBefore(meter, input.nextSibling);

        input.addEventListener('input', function() {
            const val = this.value;
            let score = 0;
            if (val.length >= 8) score++;
            if (val.length >= 12) score++;
            if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score++;
            if (/\d/.test(val)) score++;
            if (/[^a-zA-Z0-9]/.test(val)) score++;

            const levels = [
                { pct: '0%', color: '#e2e8f0', text: '' },
                { pct: '20%', color: '#dc2626', text: 'Very Weak' },
                { pct: '40%', color: '#ea580c', text: 'Weak' },
                { pct: '60%', color: '#ca8a04', text: 'Fair' },
                { pct: '80%', color: '#16a34a', text: 'Strong' },
                { pct: '100%', color: '#059669', text: 'Very Strong' },
            ];
            const level = levels[Math.min(score, 5)];
            fill.style.width = level.pct;
            fill.style.background = level.color;
            label.style.color = level.color;
            label.textContent = val.length > 0 ? level.text : '';
        });
    });
});
