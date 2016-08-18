# Create orders from Klarna into Magento

- Place KlarnaPushMagento script into root public html folder.
- Create config file and add values

NOTES:
- For users with account in store we update billing info and create new shipping address
- Every time we edit user we add klarna address (so maybe we create some duplicates). TODO check if exist before create new one.
- Check customer associate to website (maybe we could add reve)

TODO:
- [x] Wrap script as magento module
- [x] Add config page in module
- [ ] Add currency code
- [x] Add tax
- [x] Fix shipping method
- [ ] Add support to different stores
- [x] Support Avenla_KlarnaCheckout 1.2.1 and Klarna Official module

