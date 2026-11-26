Mrs Chow's Kitchen — website (HTML + admin)

Upload the whole folder to your web host (needs PHP 8.0+, which almost every host has).
Then immediately open https://your-site/admin/ and choose a password.
In the admin you can change the menu, prices, photos, hours, contact details and everything else,
then press "Save & publish". Full guide: documentation.html, section "The admin".

The site has two pages: index.html (home) and menu.html (the full menu, 102 dishes).
Prices show as [00] until you enter them in the admin (Menu tab).

Still to fill in (in the admin; they show in [BRACKETS]): phone number, opening hours, year opened,
delivery areas and fees, parking, ordering link, social handle, prices, real reviews.

Licences
* Spell of Asia font (assets/fonts): FREE FOR PERSONAL USE. A restaurant website is commercial use:
  buy a commercial licence from the designer (CAT.B) before launch, or remove the @font-face line at the
  top of assets/css/style.css to fall back to Ma Shan Zheng.
* Photos (assets/img/photos): Unsplash Licence — free to use on this website. Two are stand-ins:
  prawn toast shows fried vegetable rolls; beef with black bean sauce shows beef and broccoli.
* Logo (assets/img/logo-*.png): property of Mrs Chow's Kitchen.

ORDERS COME IN ON WHATSAPP (no online payment, no order server)
1. Admin: set the restaurant's real WhatsApp number. The cart page sends every order to it.
2. Guests add dishes on the menu, open the cart, enter their name, choose collection or delivery
   (delivery asks for an address), when, and kitchen notes, then press "Send order on WhatsApp".
   WhatsApp opens with the whole order typed out; they press Send.
3. Reply in WhatsApp to confirm. Payment is by cash or card on collection or delivery.
4. Add real prices in Admin → Menu so the order slip and the message show totals (optional).
