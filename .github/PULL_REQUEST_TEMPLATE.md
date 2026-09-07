## What this changes

<!-- One or two sentences. Link the issue if there is one. -->

## Why

<!-- The problem being solved. -->

## Checklist

- [ ] `composer lint` passes
- [ ] `composer test:unit` and `composer test:security` pass
- [ ] New or changed endpoints check a capability inside the handler
- [ ] Responses go through `API\Response`
- [ ] Docs updated (`docs/`, and `docs/API_REFERENCE.md` for endpoint changes)
- [ ] `CHANGELOG.md` updated under **Unreleased**
- [ ] No credentials, cookies, site dumps or scratch files in the diff

## Security impact

<!-- Does this widen what an authenticated caller can do? Which capability and
     config flag gate it? Write "none" if it does not apply. -->
