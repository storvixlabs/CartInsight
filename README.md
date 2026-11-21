# Magento 2 Abandoned Cart Report – Checkout Funnel & Payment Failure Analytics (StorvixLabs_CartInsight)

**StorvixLabs_CartInsight** is a free, lightweight Magento 2 abandoned cart report extension. It shows where
customers drop off in checkout, how much revenue is lost in abandoned carts, and which payment methods fail, all
without a marketing suite, extra tables or cron jobs.

> Need help reducing cart abandonment, priority support or a custom feature?
> Email **[storvixlabs@gmail.com](mailto:storvixlabs@gmail.com)**.

- **Report:** Reports > Marketing > **Cart Insight**
- **Configuration:** Stores > Configuration > Sales > **Cart Insight**

## Features

For a chosen period (default the last 30 days) and an "abandoned after N hours" threshold:

- **Abandoned cart summary:** abandoned carts, their total and average value, cart abandonment rate
  (abandoned ÷ abandoned + placed orders) and how many carts have an email address and can be followed up.
- **Checkout funnel – where customers drop off:** a funnel from the cart to the placed order. A cart's furthest step
  is read from its data: address entered, shipping method chosen, payment method chosen. Shows how many reached each
  step and how many were lost there.
- **Abandoned cart value ranges:** carts and money by value range. The ranges are configurable.
- **Payment method failure analysis:** carts lost after choosing a method, orders placed with it, and how many of
  those orders failed (canceled, still pending payment, or in payment review), with a failure rate.
- **Largest abandoned carts** (top 50) and a **CSV export** of up to 5,000 carts.
- **Zero overhead:** no new tables and no cron. The report reads the existing quote and order tables when the page is opened.

## Installation

```bash
bin/magento module:enable StorvixLabs_CartInsight
bin/magento setup:upgrade
bin/magento cache:flush
```

## Notes

- Values are in the base currency. Dates are database (UTC) dates.
- A cart counts as abandoned while it is active (not turned into an order) and has items. Magento deletes
  old quotes through *Stores > Configuration > Sales > Checkout > Quote lifetime* cleanup, so very old carts disappear.
- Cart steps for virtual-only carts use the billing address and have no shipping step.

## FAQ

**How do I see abandoned carts in Magento 2?**
Install Cart Insight and open Reports > Marketing > Cart Insight. It lists abandoned carts for the chosen period,
with their value, furthest checkout step and whether an email address is available.

**How is the cart abandonment rate calculated?**
Abandoned carts ÷ (abandoned carts + placed orders) for the selected period, using the "abandoned after N hours"
threshold you set.

**Which checkout step loses the most customers?**
The checkout funnel shows how many carts reached each step (address, shipping method, payment method, order) and
how many were lost at each one.

**Can I find failing payment methods?**
Yes. The payment methods section shows, per method, carts abandoned after choosing it and orders that ended up
canceled, pending payment or in payment review, with a failure rate.

**Does it slow down my store?**
No. It adds no tables and no cron jobs. Data is read only when an admin opens the report.

## Support & Contact

- **Bug reports and questions:** open a GitHub issue (free, best effort).
- **Checkout and conversion fixes, installation, priority support, custom features or Magento development:**
  [storvixlabs@gmail.com](mailto:storvixlabs@gmail.com)

## License

Copyright (c) 2026 StorvixLabs. Licensed, at your option, under the
[MIT License](LICENSE_MIT.txt) or the [Open Software License 3.0](LICENSE_OSL-3.0.txt).
