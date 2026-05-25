import '../style.scss';

import $ from 'jquery';
window.$ = window.jQuery = $;

import 'bootstrap';

window.addEventListener("load", () => {
    document.documentElement.classList.add("loaded");
});


const toggle = document.getElementById('menuToggle');
const menu = document.getElementById('sideMenu');
const overlay = document.getElementById('overlay');

function toggleMenu() {
    const open = menu.classList.toggle('active');
    overlay.classList.toggle('active', open);
    toggle.classList.toggle('active', open);
    document.body.style.overflow = open ? 'hidden' : '';
}

toggle.addEventListener('click', toggleMenu);
overlay.addEventListener('click', toggleMenu);

// Stagger cards on scroll
const cards = document.querySelectorAll('.service-card');
const io = new IntersectionObserver(entries => {
    entries.forEach((e, i) => {
        if (e.isIntersecting) {
            setTimeout(() => {
                e.target.style.opacity = '1';
                e.target.style.transform = 'translateY(0)';
            }, (parseInt(e.target.dataset.i) || 0) * 80);
            io.unobserve(e.target);
        }
    });
}, { threshold: 0.15 });

cards.forEach((c, i) => {
    c.dataset.i = i;
    c.style.opacity = '0';
    c.style.transform = 'translateY(24px)';
    c.style.transition = 'opacity 0.5s, transform 0.5s';
    io.observe(c);
});
