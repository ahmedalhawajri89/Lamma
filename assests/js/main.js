// Function to load HTML components
async function loadComponents() {
  const components = [
      { id: 'header-placeholder', url: 'assests/components/header.html' },
      { id: 'footer-placeholder', url: 'assests/components/footer.html' },
      { id: 'modals-placeholder', url: 'assests/components/modals.html' }
  ];

  const promises = components.map(async (component) => {
      const element = document.getElementById(component.id);
      if (element) {
          try {
              const response = await fetch(component.url);
              if (response.ok) {
                  element.innerHTML = await response.text();
              }
          } catch (error) {
              console.error(`Error loading ${component.url}:`, error);
          }
      }
  });

  await Promise.all(promises);
  initializeGlobalScripts(); // Run scripts that depend on loaded components
  initScrollReveal(); // Initialize reveal animations after DOM is fully built
}

function initializeGlobalScripts() {
// Navigation Active State Logic (Robust Exact Match)
const path = window.location.pathname;
const currentPage = path.split('/').pop() || 'index.html'; // Default to index.html if at root
const navItems = document.querySelectorAll('.nav-item, .kfl-nav-link');

navItems.forEach(item => {
  item.classList.remove('active');
  const href = item.getAttribute('href');
  
  if (href === currentPage) {
    item.classList.add('active');
  }
});

// Mobile Menu Toggle
const menuToggle = document.querySelector('.menu-toggle, .kfl-menu-toggle-modern, .kfl-menu-btn');
const navLinks = document.querySelector('.nav-links, .kfl-nav-wrapper, .kfl-nav-layout');
const header = document.querySelector('.header, .kfl-header-modern, .kfl-header-premium');

if (menuToggle && navLinks && header) {
  menuToggle.addEventListener('click', function() {
    this.classList.toggle('active');
    navLinks.classList.toggle('active');
    header.classList.toggle('menu-open');
  });

  // Close menu when clicking on a link
  const navLinksItems = navLinks.querySelectorAll('a');
  navLinksItems.forEach(link => {
    link.addEventListener('click', function() {
      menuToggle.classList.remove('active');
      navLinks.classList.remove('active');
      header.classList.remove('menu-open');
    });
  });

  // Close menu when clicking outside
  document.addEventListener('click', function(event) {
    const isClickInsideNav = navLinks.contains(event.target);
    const isClickOnToggle = menuToggle.contains(event.target);
    
    if (!isClickInsideNav && !isClickOnToggle && navLinks.classList.contains('active')) {
      menuToggle.classList.remove('active');
      navLinks.classList.remove('active');
      header.classList.remove('menu-open');
    }
  });
}

// Add scroll listener for sticky header
window.addEventListener('scroll', () => {
  const mainHeader = document.getElementById('mainHeader');
  if (mainHeader) {
    if (window.scrollY > 20) {
      mainHeader.classList.add('scrolled');
    } else {
      mainHeader.classList.remove('scrolled');
    }
  }
});

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      target.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }
  });
});

// Modal System
const modalTriggers = document.querySelectorAll('[data-modal]');
const modals = document.querySelectorAll('.modal');
const modalCloses = document.querySelectorAll('.modal-close');
const switchModals = document.querySelectorAll('.switch-modal');

// Open modal function
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    // Close all modals first
    modals.forEach(m => m.classList.remove('active'));
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
    // Open the target modal
    setTimeout(() => {
      modal.classList.add('active');
    }, 10);
  }
}

// Close modal function
function closeModal(modal) {
  modal.classList.remove('active');
  document.body.style.overflow = '';
}

// Open modal on button click
modalTriggers.forEach(trigger => {
  trigger.addEventListener('click', function(e) {
    e.preventDefault();
    const modalId = this.getAttribute('data-modal') + 'Modal';
    openModal(modalId);
  });
});

// Close modal on close button click
modalCloses.forEach(closeBtn => {
  closeBtn.addEventListener('click', function() {
    const modal = this.closest('.modal');
    closeModal(modal);
  });
});

// Close modal on overlay click
modals.forEach(modal => {
  const overlay = modal.querySelector('.modal-overlay');
  if (overlay) {
    overlay.addEventListener('click', function() {
      closeModal(modal);
    });
  }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    modals.forEach(modal => {
      if (modal.classList.contains('active')) {
        closeModal(modal);
      }
    });
  }
});

// Switch between modals
switchModals.forEach(link => {
  link.addEventListener('click', function(e) {
    e.preventDefault();
    const targetModalId = this.getAttribute('data-target');
    if (targetModalId) {
      // Close current modal
      const currentModal = this.closest('.modal');
      closeModal(currentModal);
      // Open target modal after a short delay
      setTimeout(() => {
        openModal(targetModalId);
      }, 300);
    }
  });
});

// Form submission handlers
const registerForm = document.getElementById('registerForm');
const loginForm = document.getElementById('loginForm');

if (registerForm) {
  registerForm.addEventListener('submit', function(e) {
    e.preventDefault();
    // Here you can add your registration logic
    console.log('Register form submitted');
    // Example: Show success message and close modal
    alert('تم إنشاء الحساب بنجاح!');
    closeModal(document.getElementById('registerModal'));
  });
}

if (loginForm) {
  loginForm.addEventListener('submit', function(e) {
    e.preventDefault();
    // Here you can add your login logic
    console.log('Login form submitted');
    // Example: Show success message and close modal
    alert('تم تسجيل الدخول بنجاح!');
    closeModal(document.getElementById('loginModal'));
  });
}

// Forgot Password Form
const forgotPasswordForm = document.getElementById('forgotPasswordForm');
if (forgotPasswordForm) {
  forgotPasswordForm.addEventListener('submit', function(e) {
    e.preventDefault();
    // Here you can add your forgot password logic
    console.log('Forgot password form submitted');
    // Example: Show success message and close modal
    alert('تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني!');
    closeModal(document.getElementById('forgotPasswordModal'));
  });
}

// Donate Modal Functionality
const donateForm = document.getElementById('donateForm');
const amountButtons = document.querySelectorAll('.amount-btn');
const customAmountInput = document.getElementById('customAmount');
const selectedAmountDisplay = document.querySelector('.selected-amount');
let selectedAmount = 0;

// Handle amount button clicks
if (amountButtons.length > 0) {
  amountButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      // Remove active class from all buttons
      amountButtons.forEach(b => b.classList.remove('active'));
      // Add active class to clicked button
      this.classList.add('active');
      // Get amount from data attribute
      selectedAmount = parseInt(this.getAttribute('data-amount'));
      // Clear custom amount input
      if (customAmountInput) {
        customAmountInput.value = '';
      }
      // Update display
      updateAmountDisplay();
    });
  });
}

// Handle custom amount input
if (customAmountInput) {
  customAmountInput.addEventListener('input', function() {
    const value = parseInt(this.value);
    if (value && value > 0) {
      selectedAmount = value;
      // Remove active class from all buttons
      amountButtons.forEach(b => b.classList.remove('active'));
      // Update display
      updateAmountDisplay();
    } else if (this.value === '') {
      selectedAmount = 0;
      updateAmountDisplay();
    }
  });
}

// Update amount display
function updateAmountDisplay() {
  if (selectedAmountDisplay) {
    selectedAmountDisplay.textContent = selectedAmount + ' ريال';
  }
}

// Handle donate form submission
if (donateForm) {
  donateForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (selectedAmount <= 0) {
      alert('الرجاء اختيار مبلغ التبرع');
      return;
    }

    const formData = {
      amount: selectedAmount,
      name: document.getElementById('donorName').value,
      email: document.getElementById('donorEmail').value,
      phone: document.getElementById('donorPhone').value,
      message: document.getElementById('donationMessage').value
    };

    // Here you can add your donation logic
    console.log('Donation submitted:', formData);
    
    // Show success message
    alert(`شكراً لك! تم تأكيد تبرعك بمبلغ ${selectedAmount} ريال. سنتواصل معك قريباً.`);
    
    // Reset form
    donateForm.reset();
    amountButtons.forEach(b => b.classList.remove('active'));
    selectedAmount = 0;
    updateAmountDisplay();
    
    // Close modal
    closeModal(document.getElementById('donateModal'));
  });
}

// Credit Card Input Formatting
const cardNumberInput = document.getElementById('cardNumber');
if (cardNumberInput) {
    cardNumberInput.addEventListener('input', (e) => {
        // Get the value without spaces and non-digits
        let rawValue = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        
        // Limit to 16 digits
        rawValue = rawValue.substring(0, 16);

        // Add spaces every 4 digits
        const parts = [];
        for (let i = 0; i < rawValue.length; i += 4) {
            parts.push(rawValue.substring(i, i + 4));
        }
        e.target.value = parts.join(' ');
    });
}
}

// Statistics Counter Animation
function animateCounter(element) {
  const target = parseInt(element.getAttribute('data-target'));
  const prefix = element.getAttribute('data-prefix') || '';
  const duration = 2000; // 2 seconds
  const startTime = Date.now();
  
  // Add animation class
  element.classList.add('animating');
  
  const updateCounter = () => {
    const elapsed = Date.now() - startTime;
    const progress = Math.min(elapsed / duration, 1);
    
    // Easing function for smooth animation
    const easeOutQuart = 1 - Math.pow(1 - progress, 4);
    const current = Math.floor(target * easeOutQuart);
    
    element.textContent = prefix + current;
    
    if (progress < 1) {
      requestAnimationFrame(updateCounter);
    } else {
      element.textContent = prefix + target;
      element.classList.remove('animating');
    }
  };

  updateCounter();
}

// Intersection Observer for Statistics Section
const statSections = document.querySelectorAll('.statistics, .statistics-modern, .hero-modern');
if (statSections.length > 0) {
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px'
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const statCounters = entry.target.querySelectorAll('.circle[data-target], .counter[data-target]');
        statCounters.forEach((circle, index) => {
          // Add delay for each counter
          setTimeout(() => {
            if (!circle.classList.contains('animating') && circle.textContent.trim() === '0' || circle.textContent.trim() === '+0') {
               animateCounter(circle);
            }
          }, index * 200);
        });
        // Unobserve after animation starts
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  statSections.forEach(section => observer.observe(section));
}

// Handle form icon focus effect
const formInputs = document.querySelectorAll('.form-group input');
formInputs.forEach(input => {
  input.addEventListener('focus', function() {
    const formGroup = this.closest('.form-group');
    const icon = formGroup.querySelector('.form-icon');
    if (icon) {
      icon.style.color = 'var(--primary-color)';
    }
  });
  
  input.addEventListener('blur', function() {
    const formGroup = this.closest('.form-group');
    const icon = formGroup.querySelector('.form-icon');
    if (icon) {
      icon.style.color = '#999';
    }
  });
});

// CTA Button with Ripple Effect and Smooth Scroll
const ctaButton = document.querySelector('.hero .btn-cta');
if (ctaButton) {
  ctaButton.addEventListener('click', function(e) {
    // Create ripple effect
    const ripple = this.querySelector('.ripple');
    if (ripple) {
      ripple.style.animation = 'none';
      setTimeout(() => {
        ripple.style.animation = 'ripple-animation 0.6s ease-out';
      }, 10);
    }
    
    // Add clicked class for animation
    this.classList.add('clicked');
    setTimeout(() => {
      this.classList.remove('clicked');
    }, 600);
    
    // Smooth scroll to section
    const targetId = this.getAttribute('href');
    if (targetId && targetId.startsWith('#')) {
      e.preventDefault();
      const targetSection = document.querySelector(targetId);
      if (targetSection) {
        const headerOffset = 80;
        const elementPosition = targetSection.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

        window.scrollTo({
          top: offsetPosition,
          behavior: 'smooth'
        });
      }
    }
  });
}

// Other Sponsorships Slider Logic
const sliderTrack = document.getElementById('otherSponsorshipsTrack');
const nextBtn = document.querySelector('.slider-btn.next-btn');
const prevBtn = document.querySelector('.slider-btn.prev-btn');

if (sliderTrack && nextBtn && prevBtn) {
  // Scroll amount (card width + gap)
  const scrollAmount = 250; 

  nextBtn.addEventListener('click', () => {
    // In RTL, scrolling "next" (visually left) means negative scroll value
    // But scrollBy behavior depends on browser implementation of RTL
    // Usually negative X moves to the left
    sliderTrack.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
  });

  prevBtn.addEventListener('click', () => {
    // In RTL, scrolling "prev" (visually right) means positive scroll value
    sliderTrack.scrollBy({ left: scrollAmount, behavior: 'smooth' });
  });
}

// Partners Logo Slider
const partnersLogosTrack = document.getElementById('partnersLogos');
if (partnersLogosTrack) {
    const logos = partnersLogosTrack.querySelectorAll('.partner-logo');
    if (logos.length > 0) {
        const logoCount = logos.length;
        let currentIndex = 0;
        let intervalId;

        function activateLogo(index) {
            // Remove active class from all
            logos.forEach(logo => logo.classList.remove('active'));
            
            // Add active class to the current logo
            const currentLogo = logos[index];
            if (currentLogo) {
                currentLogo.classList.add('active');

                // Calculate the translation needed to center the active logo
                const trackContainer = partnersLogosTrack.parentElement;
                const containerWidth = trackContainer.offsetWidth;
                const logoWidth = currentLogo.offsetWidth;
                const logoOffsetLeft = currentLogo.offsetLeft;
                
                const translation = (containerWidth / 2) - (logoWidth / 2) - logoOffsetLeft;
                
                partnersLogosTrack.style.transform = `translateX(${translation}px)`;
            }
        }

        function startSlider() {
            clearInterval(intervalId); // Stop any existing interval
            intervalId = setInterval(() => {
                currentIndex = (currentIndex + 1) % logoCount;
                activateLogo(currentIndex);
            }, 3000); // 3 seconds
        }

        // A small delay to ensure layout is calculated correctly before the first run
        setTimeout(() => {
            // Start with the middle logo for a better initial view
            currentIndex = Math.floor(logoCount / 2);
            activateLogo(currentIndex);
            startSlider();
        }, 500);
    }
}

// FAQ Accordion
const faqItems = document.querySelectorAll('.faq-item');
faqItems.forEach(item => {
  const question = item.querySelector('.faq-question');
  if (question) {
    question.addEventListener('click', () => {
      item.classList.toggle('active');
    });
  }
});

// Scroll Reveal Animation System
function initScrollReveal() {
    const revealElements = document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale');

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
                revealObserver.unobserve(entry.target); 
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: "0px 0px -50px 0px"
    });

    revealElements.forEach(element => {
        revealObserver.observe(element);
    });
}

// Initialize App
document.addEventListener('DOMContentLoaded', () => {
  loadComponents();
  initParallax();
});

// 3D Parallax Mouse Effect for Hero Section
function initParallax() {
  const heroScene = document.getElementById('heroScene');
  if (!heroScene) return;

  const floatCards = heroScene.querySelectorAll('.float-card');
  const mainCard = heroScene.querySelector('.main-image-card');

  heroScene.addEventListener('mousemove', (e) => {
    const rect = heroScene.getBoundingClientRect();
    const x = e.clientX - rect.left; // x position within the element
    const y = e.clientY - rect.top;  // y position within the element

    const centerX = rect.width / 2;
    const centerY = rect.height / 2;

    const percentX = (x - centerX) / centerX;
    const percentY = (y - centerY) / centerY;

    // Rotate Main Card slightly for 3D effect
    if(mainCard) {
      const rotateX = percentY * 5; // max 5 deg
      const rotateY = percentX * -5;
      mainCard.style.transform = `translate(-50%, -50%) perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
    }

    // Move floating cards in parallax
    floatCards.forEach(card => {
      const speed = card.getAttribute('data-speed') || 1;
      const moveX = percentX * speed * 20; // 20px max movement
      const moveY = percentY * speed * 20;
      card.style.transform = `translate(${moveX}px, ${moveY}px)`;
    });
  });

  heroScene.addEventListener('mouseleave', () => {
    // Reset transforms smoothly
    if(mainCard) {
      mainCard.style.transition = 'transform 0.5s var(--kfl-ease-out)';
      mainCard.style.transform = `translate(-50%, -50%) perspective(1000px) rotateX(0deg) rotateY(0deg)`;
      setTimeout(() => { mainCard.style.transition = ''; }, 500); // Remove transition to avoid delay on mouseenter
    }
    floatCards.forEach(card => {
      card.style.transition = 'transform 0.5s var(--kfl-ease-out)';
      card.style.transform = `translate(0, 0)`;
      setTimeout(() => { card.style.transition = ''; }, 500);
    });
  });
}