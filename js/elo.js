const translations = {
              hu: {
                  "nav.home": "Főoldal", "nav.live": "Élő", "nav.bonuses": "Bónuszok",
                  "nav.help": "Segítség", "nav.login": "Bejelentkezés",
                  "nav.register": "Regisztráció",
                  "footer.copyright": "Élményt nyújtunk, értéket teremtünk © BetMatchBonus – 2025. Minden jog fenntartva.",
                  "lang.title.hu": "Magyar", "lang.title.en": "Angol"
              },
              en: {
                  "nav.home": "Home", "nav.live": "Live", "nav.bonuses": "Bonuses",
                  "nav.help": "Help", "nav.login": "Login", "nav.register": "Register",
                  "footer.copyright": "We provide experience, create value © BetMatchBonus – 2025. All rights reserved.",
                  "lang.title.hu": "Hungarian", "lang.title.en": "English"
              }
          };

          let currentLang = 'hu';

          function changeLanguage(lang) {
              currentLang = lang;
              
              document.querySelectorAll('[data-i18n]').forEach(element => {
                  const key = element.getAttribute('data-i18n');
                  if (translations[lang] && translations[lang][key]) {
                      element.textContent = translations[lang][key];
                  }
              });
              
              const huBtn = document.getElementById('lang-hu');
              const enBtn = document.getElementById('lang-en');
              
              huBtn.classList.remove('active');
              enBtn.classList.remove('active');
              
              if (lang === 'hu') {
                  huBtn.classList.add('active');
              } else {
                  enBtn.classList.add('active');
              }
              
              huBtn.title = translations[lang]["lang.title.hu"];
              enBtn.title = translations[lang]["lang.title.en"];
              
              localStorage.setItem('preferred-language', lang);
              document.documentElement.lang = lang;
          }

          document.getElementById('lang-hu').addEventListener('click', () => changeLanguage('hu'));
          document.getElementById('lang-en').addEventListener('click', () => changeLanguage('en'));

          document.addEventListener('DOMContentLoaded', () => {
              const savedLang = localStorage.getItem('preferred-language');
              if (savedLang && (savedLang === 'hu' || savedLang === 'en')) {
                  changeLanguage(savedLang);
              }
          });