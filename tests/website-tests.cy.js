describe('Miracle Nature Labs Website Tests', () => {
  const pages = [
    '/',
    '/terms.html',
    '/privacy.html',
    '/contact.php'
  ]

  pages.forEach(page => {
    describe(`Page: ${page}`, () => {
      beforeEach(() => {
        cy.visit(page)
      })

      it('should load the page successfully', () => {
        cy.url().should('include', page)
      })

      it('should not have missing images', () => {
        cy.get('img').each($img => {
          const src = $img.attr('src')
          if (src) {
            cy.request(src).its('status').should('eq', 200)
          }
        })
      })

      it('should not have broken links', () => {
        cy.get('a').each($a => {
          const href = $a.attr('href')
          if (href && !href.startsWith('#') && !href.startsWith('mailto:') && !href.startsWith('tel:')) {
            cy.request(href).its('status').should('be.oneOf', [200, 301, 302])
          }
        })
      })

      it('should display the favicon', () => {
        cy.get('link[rel="icon"], link[rel="shortcut icon"], link[rel="apple-touch-icon"]').first().then($link => {
          const href = $link.attr('href')
          expect(href, 'favicon link should exist').to.be.a('string').and.not.be.empty
          cy.request(href).its('status').should('eq', 200)
        })
      })

      it('should have SEO meta tags', () => {
        cy.get('head title').should('contain.text', 'Miracle Nature Labs')
        cy.get('meta[name="viewport"]').should('have.attr', 'content').and('include', 'width=device-width')
        cy.get('meta[name="description"]').should('exist').and('have.attr', 'content').and('not.be.empty')
      })

      it('should have heading and accessible images', () => {
        cy.get('h1').should('exist').and('not.be.empty')
        cy.get('img').each($img => {
          const alt = $img.attr('alt')
          expect(alt, 'image alt text').to.be.a('string').and.not.be.empty
        })
      })

      it('should not have links without text', () => {
        cy.get('a').each($a => {
          const text = $a.text().trim()
          const hasImg = $a.find('img').length > 0
          expect(text.length > 0 || hasImg, 'link has text or image').to.be.true
        })
      })

      if (page === '/contact.php') {
        it('should have a working contact form', () => {
          cy.get('form').should('exist')
          cy.get('input[type="email"]').should('exist')
          cy.get('input[required], textarea[required]').should('exist')
        })

        it('should show validation errors for empty required fields', () => {
          cy.get('form').then($form => {
            cy.wrap($form).find('[type="submit"]').click()
            cy.get('.error-list').should('exist').and('contain.text', 'Please enter')
          })
        })
      }

      it('should log page load time', () => {
        cy.window().then(win => {
          const perf = win.performance || win.webkitPerformance || win.msPerformance || win.mozPerformance
          const timing = perf?.timing
          expect(timing, 'performance timing object should exist').to.exist
          if (timing) {
            const loadTime = timing.loadEventEnd - timing.navigationStart
            cy.log(`Page ${page} load time: ${loadTime}ms`)
            expect(loadTime, 'load time should be > 0').to.be.greaterThan(0)
          }
        })
      })
    })
  })
})