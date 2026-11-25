document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});

const auditInputs = Array.from(
    document.querySelectorAll('[data-audit-answer]')
);

if (auditInputs.length > 0) {
    const weights = new Map();

    document.querySelectorAll('[data-weight]').forEach((cell) => {
        const row = cell.closest('tr');
        const checkbox = row && row.querySelector('[data-audit-answer]');

        if (checkbox) {
            weights.set(checkbox.dataset.auditAnswer, Number(cell.dataset.weight));
        }
    });

    function updateAuditScore() {
        let score = 0;
        let reviewed = 0;

        auditInputs.forEach((input) => {
            if (input.dataset.auditValue !== 'yes' || !input.checked) {
                return;
            }

            score += weights.get(input.dataset.auditAnswer) || 0;
        });

        const answers = new Map();
        auditInputs.forEach((input) => {
            if (input.checked) {
                answers.set(input.dataset.auditAnswer, input.dataset.auditValue);
            }
        });

        answers.forEach((answer, code) => {
            if (answer === 'yes' || answer === 'no') {
                reviewed += 1;
            }

            const label = document.querySelector(`[data-pending="${code}"]`);
            if (label) {
                label.textContent = '';
            }
        });

        document.querySelectorAll('[data-pending]').forEach((label) => {
            if (!answers.has(label.dataset.pending)) {
                label.textContent = 'Belum Diperiksa';
            }
        });

        const maximum = Array.from(weights.values()).reduce(
            (total, weight) => total + weight,
            0
        );
        const scoreTarget = document.getElementById('audit-score');
        const percentTarget = document.getElementById('audit-percent');
        const reviewedTarget = document.getElementById('audit-reviewed');

        if (scoreTarget) {
            scoreTarget.textContent = score.toFixed(2);
        }
        if (percentTarget) {
            percentTarget.textContent = maximum > 0
                ? ((score / maximum) * 100).toFixed(1)
                : '0.0';
        }
        if (reviewedTarget) {
            reviewedTarget.textContent = String(reviewed);
        }
    }

    auditInputs.forEach((input) => {
        input.addEventListener('change', () => {
            if (input.checked) {
                const oppositeValue = input.dataset.auditValue === 'yes'
                    ? 'no'
                    : 'yes';

                auditInputs.forEach((otherInput) => {
                    if (
                        otherInput.dataset.auditAnswer === input.dataset.auditAnswer &&
                        otherInput.dataset.auditValue === oppositeValue
                    ) {
                        otherInput.checked = false;
                    }
                });
            }

            updateAuditScore();
        });
    });

    updateAuditScore();
}
