    <!--=============== FOOTER ===============-->
    <footer class="footer container">
      <div class="footer__container grid">
        <div class="footer__content">
          <h4 class="footer__title">Contact</h4>
          <p class="footer__description"><span>Address:</span> TARUMT, Jalan Genting Kelang, Setapak, 53300 Kuala Lumpur, Federal Territory of Kuala Lumpur</p>
          <p class="footer__description"><span>Phone:</span> +01 2222 365 /(+91) 01 2345 6789</p>
          <p class="footer__description"><span>Hours:</span> 10:00 - 18:00, Mon - Sat</p>

          <div class="footer__social">
            <h4 class="footer__subtitle">Follow Me</h4>
            <div class="footer__social-links">
              <a href="https://www.facebook.com/tarumtkl/"><img src="./assets/img/icon-facebook.svg" alt="" class="footer__social-icon" /></a>
              <a href="https://x.com/KlTaruc"><img src="./assets/img/icon-twitter.svg" alt="" class="footer__social-icon" /></a>
              <a href="https://www.instagram.com/tarumt.official/?hl=en"><img src="./assets/img/icon-instagram.svg" alt="" class="footer__social-icon" /></a>
              <a href="https://www.youtube.com/channel/UCqjsPpVnwjCRT5mgAgFo1ng"><img src="./assets/img/icon-youtube.svg" alt="" class="footer__social-icon" /></a>
            </div>
          </div>
        </div>

        <div class="footer__content">
          <h3 class="footer__title">Our Story</h3>
          <p>Founded in 1995, TOYWORLD began as a small family-owned toy store with a simple mission: to bring joy and wonder to children of all ages. What started as a single location has grown into a beloved toy destination across multiple cities, but our commitment to quality, imagination, and exceptional customer service remains unchanged.
          </p>
        </div>

        <div class="footer__content">
          <h3 class="footer__title">Secured Payment Gateways</h3>
          <img src="./assets/img/payment-method.png" alt="" class="payment__img" />
        </div>
      </div>

      <div class="footer__bottom">
        <p class="copyright">&copy; 2024 Evara. All right reserved</p>
        <span class="designer">Designed by Crypticalcoder</span>
      </div>
    </footer>

    <!--=============== MAIN JS ===============-->
    <script src="./assets/js/main.js"></script>


    <!--=============== Chatbot ===============-->

    <script>
      (function() {
        if (!window.chatbase || window.chatbase("getState") !== "initialized") {
          window.chatbase = (...arguments) => {
            if (!window.chatbase.q) {
              window.chatbase.q = []
            }
            window.chatbase.q.push(arguments)
          };
          window.chatbase = new Proxy(window.chatbase, {
            get(target, prop) {
              if (prop === "q") {
                return target.q
              }
              return (...args) => target(prop, ...args)
            }
          })
        }
        const onLoad = function() {
          const script = document.createElement("script");
          script.src = "https://www.chatbase.co/embed.min.js";
          script.id = "IvGsewnJftCRERPpfQqAY";
          script.domain = "www.chatbase.co";
          document.body.appendChild(script)
        };
        if (document.readyState === "complete") {
          onLoad()
        } else {
          window.addEventListener("load", onLoad)
        }
      })();
    </script>

    </body>

    </html>